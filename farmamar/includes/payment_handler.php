<?php
function encryptMercantil($data, $keyBase) {
    // Derivar clave SHA-256 y tomar los primeros 16 bytes (como hacíamos en JS)
    $keyHash = hash('sha256', $keyBase, true);
    $keyBytes = substr($keyHash, 0, 16);
    
    // Cifrar en AES-128-ECB
    $encrypted = openssl_encrypt($data, 'aes-128-ecb', $keyBytes, OPENSSL_RAW_DATA);
    return base64_encode($encrypted);
}

function verifyPayment($formData, $config) {
    $origin_mobile = "58" . $formData['phone_prefix'] . $formData['phone_number'];
    
    $payload = [
        "merchant_identify" => [
            "integratorId" => (int)$config['integrator_id'],
            "merchantId" => (int)$config['merchant_id'],
            "terminalId" => $config['terminal_id'] // Usa la variable del config
        ],
        "client_identify" => [
            "ipaddress" => $_SERVER['REMOTE_ADDR'], // Usa la IP real del cliente
            "browser_agent" => substr($_SERVER['HTTP_USER_AGENT'] ?? 'Chrome/1.0', 0, 80), // Limita a 80 caracteres
            "mobile" => [
                "manufacturer" => substr("Samsung", 0, 80), // Limita a 80 caracteres
                "model" => substr("Galaxy", 0, 80),
                "os_version" => substr("Android 10", 0, 80)
            ]
        ],
        "search_by" => [
            "amount" => (float)$formData['amount'],
            "currency" => "ves",
            "destination_mobile_number" => encryptMercantil($config['destination_mobile'], $config['aes_key']),
            "origin_mobile_number" => encryptMercantil($origin_mobile, $config['aes_key']),
            "payment_reference" => (string)$formData['reference'],
            "trx_date" => $formData['date']
        ]
    ];

    $ch = curl_init($config['api_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-IBM-Client-ID: ' . $config['client_id']
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Temporal para debugging
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Temporal para debugging
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Para ver detalles de la conexión
    
    // Capturar información de la respuesta HTTP
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    
    // Separar headers del body
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Log para debugging
    error_log("HTTP Code: " . $http_code);
    error_log("Headers: " . $headers);
    error_log("Body: " . $body);
    
    $decoded = json_decode($body, true);
    
    // Si no se pudo decodificar JSON, intenta limpiar caracteres extraños
    if ($decoded === null && $body !== null) {
        // Eliminar caracteres BOM u otros caracteres extraños
        $body = preg_replace('/[[:cntrl:]]/', '', $body);
        $decoded = json_decode($body, true);
    }
    
    return [
        'http_code' => $http_code,
        'headers' => $headers,
        'raw_response' => $body,
        'decoded_response' => $decoded,
        'error' => $error
    ];
}

function saveVerifiedTransaction($pdo, $usuario_id, $formData, $apiResponse, $config) {
    try {
        // Extraer datos de la respuesta de la API
        $transactionData = null;
        if (isset($apiResponse['transaction_list']) && !empty($apiResponse['transaction_list'])) {
            $transactionData = $apiResponse['transaction_list'][0];
        }
        
        $sql = "INSERT INTO transacciones_verificadas (
            usuario_id,
            fecha_transaccion,
            monto,
            referencia,
            cedula_cliente,
            prefijo_cedula,
            telefono,
            autorizacion,
            metodo_pago,
            banco_destino,
            factura,
            estado,
            respuesta_api
        ) VALUES (
            :usuario_id,
            :fecha_transaccion,
            :monto,
            :referencia,
            :cedula_cliente,
            :prefijo_cedula,
            :telefono,
            :autorizacion,
            :metodo_pago,
            :banco_destino,
            :factura,
            :estado,
            :respuesta_api
        )";
        
        $stmt = $pdo->prepare($sql);
        
        // Determinar estado
        $estado = 'PENDIENTE';
        if ($transactionData) {
            if (in_array(strtolower($transactionData['trx_type']), ['p2p_payment', 'payment', 'pago', 'transfer'])) {
                $estado = 'VERIFICADO';
            } else {
                $estado = 'RECHAZADO';
            }
        }
        
        $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':fecha_transaccion' => $formData['date'],
            ':monto' => $formData['amount'],
            ':referencia' => $formData['reference'],
            ':cedula_cliente' => $formData['customer_id'],
            ':prefijo_cedula' => $formData['id_prefix'] ?? 'V',
            ':telefono' => ($formData['phone_prefix'] ?? '') . ($formData['phone_number'] ?? ''),
            ':autorizacion' => $transactionData['authorization_code'] ?? null,
            ':metodo_pago' => $transactionData['payment_method'] ?? null,
            ':banco_destino' => $transactionData['destination_bank_id'] ?? null,
            ':factura' => $transactionData['invoice_number'] ?? null,
            ':estado' => $estado,
            ':respuesta_api' => json_encode($apiResponse)
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error al guardar transacción: " . $e->getMessage());
        return false;
    }
}