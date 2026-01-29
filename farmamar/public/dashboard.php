<?php
// Configurar UTF-8 al inicio del archivo
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// Función para asegurar encoding correcto
function safeText($text) {
    if (mb_check_encoding($text, 'UTF-8')) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars(mb_convert_encoding($text, 'UTF-8', 'auto'), ENT_QUOTES, 'UTF-8');
}

require_once '../includes/auth.php';
require_once '../includes/payment_handler.php';
$config = require_once '../config/api_config.php';
checkSession();

$apiResponse = null;
$httpCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = verifyPayment($_POST, $config);
    $httpCode = $result['http_code'];
    $apiResponse = $result['decoded_response'];
    $rawResponse = $result['raw_response'];
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificador Pago Móvil - Farmamar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #004a99;
            --primary-light: #0066cc;
            --primary-dark: #003366;
            --secondary: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --border-radius: 10px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
            color: var(--dark);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .company-info h1 {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .company-info p {
            color: var(--gray);
            font-size: 14px;
        }

        .user-info {
            text-align: right;
            background: var(--light);
            padding: 10px 20px;
            border-radius: var(--border-radius);
        }

        .user-info strong {
            color: var(--primary);
        }

        .logout-btn {
            display: inline-block;
            margin-top: 8px;
            color: var(--danger);
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
        }

        .logout-btn:hover {
            text-decoration: underline;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            font-size: 20px;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .card-body {
            padding: 30px;
        }

        /* Formulario mejorado */
        .form-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        @media (max-width: 768px) {
            .form-container {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        @media (max-width: 768px) {
            .form-group.full-width {
                grid-column: span 1;
            }
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            outline: none;
            font-size: 15px;
            color: var(--dark);
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 74, 153, 0.1);
        }

        select.form-control {
            background: white;
            cursor: pointer;
        }

        .form-actions {
            grid-column: span 2;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .form-actions {
                grid-column: span 1;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-2px);
        }

        /* Resultados */
        .status-card {
            margin-bottom: 15px;
            border-left: 5px solid transparent;
            padding: 20px;
            border-radius: var(--border-radius);
        }

        .status-success {
            border-left-color: var(--secondary);
            background: linear-gradient(135deg, #f8fff9 0%, #e8f7eb 100%);
        }

        .status-error {
            border-left-color: var(--danger);
            background: linear-gradient(135deg, #fff8f8 0%, #fce8e8 100%);
        }

        .status-warning {
            border-left-color: var(--warning);
            background: linear-gradient(135deg, #fffbf0 0%, #fef5e7 100%);
        }

        .status-info {
            border-left-color: var(--primary);
            background: linear-gradient(135deg, #f0f8ff 0%, #e8f1f9 100%);
        }

        .transaction-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .detail-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .detail-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }

        .detail-value.amount {
            color: var(--secondary);
            font-size: 22px;
        }

        .detail-value.reference {
            color: var(--primary);
            font-family: monospace;
            font-size: 18px;
        }

        .icon-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .icon-badge.success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--secondary);
        }

        .icon-badge.error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .icon-badge.warning {
            background: rgba(255, 193, 7, 0.1);
            color: #e0a800;
        }

        .icon-badge.info {
            background: rgba(0, 74, 153, 0.1);
            color: var(--primary);
        }

        .response-title {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .response-title h4 {
            font-size: 18px;
            margin: 0;
        }

        .response-title small {
            display: block;
            color: var(--gray);
            font-size: 14px;
            margin-top: 5px;
        }

        .http-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .http-200 {
            background: #d4edda;
            color: #155724;
        }

        .http-400 {
            background: #f8d7da;
            color: #721c24;
        }

        .http-500 {
            background: #f8d7da;
            color: #721c24;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .alert-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .alert-title i {
            font-size: 20px;
        }

        .debug-toggle {
            background: var(--light);
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            color: var(--gray);
            cursor: pointer;
            font-size: 12px;
            margin-top: 15px;
            transition: var(--transition);
        }

        .debug-toggle:hover {
            background: #e9ecef;
        }

        .debug-content {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            overflow: auto;
            max-height: 300px;
        }

        pre {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            white-space: pre-wrap;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 30px;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <div class="logo">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="company-info">
                    <h1><?php echo safeText('Verificador de Pagos Móvil'); ?></h1>
                    <p><?php echo safeText('Farmamar - Sistema de Verificación C2P'); ?></p>
                </div>
            </div>
            <div class="user-info">
                <i class="fas fa-user-circle"></i> 
                <strong><?php echo safeText($_SESSION['user_name']); ?></strong>
                <br>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> <?php echo safeText('Cerrar Sesión'); ?>
                </a>
            </div>
        </div>

        <!-- Barra de estadísticas (opcional, puedes eliminar si no la quieres) -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    // Puedes agregar un contador de consultas si lo deseas
                    if (isset($_SESSION['query_count'])) {
                        echo $_SESSION['query_count'];
                    } else {
                        echo "0";
                    }
                    ?>
                </div>
                <div class="stat-label">Consultas Realizadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo date('d/m/Y'); ?></div>
                <div class="stat-label">Fecha Actual</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        echo '<i class="fas fa-check-circle text-success"></i>';
                    } else {
                        echo '<i class="fas fa-circle text-muted"></i>';
                    }
                    ?>
                </div>
                <div class="stat-label">Estado API</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Form Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-search"></i>
                    <h3><?php echo safeText('Consulta de Pago Móvil'); ?></h3>
                </div>
                <div class="card-body">
                    <form id="paymentForm" method="POST" class="form-container">
                        <!-- Cliente Info -->
                        <div class="form-group full-width">
                            <label><i class="fas fa-id-card"></i> <?php echo safeText('Cédula del Cliente'); ?></label>
                            <div class="form-row">
                                <div class="form-group" style="flex: 0 0 100px;">
                                    <select name="id_prefix" class="form-control">
                                        <option value="V">V</option>
                                        <option value="J">J</option>
                                        <option value="G">G</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <input type="number" name="customer_id" class="form-control" placeholder="<?php echo safeText('Ej: 12345678'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Teléfono -->
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> <?php echo safeText('Teléfono Origen'); ?></label>
                            <div class="form-row">
                                <div class="form-group" style="flex: 0 0 140px;">
                                    <select name="phone_prefix" class="form-control">
                                        <option value="414">0414</option>
                                        <option value="424">0424</option>
                                        <option value="412">0412</option>
                                        <option value="416">0416</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <input type="number" name="phone_number" class="form-control" placeholder="1234567" required>
                                </div>
                            </div>
                        </div>

                        <!-- Monto -->
                        <div class="form-group">
                            <label><i class="fas fa-money-bill-wave"></i> <?php echo safeText('Monto (Bs.)'); ?></label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        </div>

                        <!-- Referencia -->
                        <div class="form-group">
                            <label><i class="fas fa-hashtag"></i> <?php echo safeText('Referencia'); ?></label>
                            <input type="number" name="reference" class="form-control" placeholder="<?php echo safeText('Últimos dígitos'); ?>" required>
                        </div>

                        <!-- Fecha -->
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> <?php echo safeText('Fecha de Transacción'); ?></label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Botón -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> <?php echo safeText('Consultar Pago'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-bar"></i>
                    <h3><?php echo safeText('Resultados de la Consulta'); ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        
                        <!-- Loading State -->
                        <div class="loading" id="loading">
                            <div class="loading-spinner"></div>
                            <p><?php echo safeText('Procesando consulta...'); ?></p>
                        </div>

                        <!-- Results Content -->
                        <div id="resultsContent">
                            <?php if (isset($error) && $error): ?>
                                <div class="status-card status-error">
                                    <div class="response-title">
                                        <div class="icon-badge error">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <h4><?php echo safeText('Error de Conexión'); ?></h4>
                                    </div>
                                    <div class="alert">
                                        <p><?php echo safeText($error); ?></p>
                                    </div>
                                </div>
                            
                            <?php elseif (isset($httpCode)): ?>
                                
                                <!-- HTTP Status -->
                                <div class="http-status http-<?php echo $httpCode; ?>">
                                    HTTP <?php echo $httpCode; ?> - <?php 
                                    if ($httpCode === 200) echo safeText('OK');
                                    elseif ($httpCode === 400) echo safeText('BAD REQUEST');
                                    elseif ($httpCode === 500) echo safeText('INTERNAL SERVER ERROR');
                                    else echo safeText('RESPONSE');
                                    ?>
                                </div>

                                <!-- Success Response -->
                                <?php if ($httpCode === 200 && is_array($apiResponse)): ?>
                                    
                                    <?php if (isset($apiResponse['error_list']) && !empty($apiResponse['error_list'])): ?>
                                        <div class="status-card status-error">
                                            <div class="response-title">
                                                <div class="icon-badge error">
                                                    <i class="fas fa-times-circle"></i>
                                                </div>
                                                <h4><?php echo safeText('Error en la Consulta'); ?></h4>
                                            </div>
                                            <?php foreach ($apiResponse['error_list'] as $err): ?>
                                                <div class="alert">
                                                    <div class="alert-title">
                                                        <i class="fas fa-exclamation-circle"></i>
                                                        <strong><?php echo safeText('Código'); ?> <?php echo $err['error_code']; ?></strong>
                                                    </div>
                                                    <p><?php echo safeText($err['description']); ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    
                                    <?php elseif (isset($apiResponse['transaction_list']) && is_array($apiResponse['transaction_list'])): ?>
                                        
                                        <?php if (empty($apiResponse['transaction_list'])): ?>
                                            <div class="status-card status-warning">
                                                <div class="response-title">
                                                    <div class="icon-badge warning">
                                                        <i class="fas fa-search"></i>
                                                    </div>
                                                    <h4><?php echo safeText('No se encontraron transacciones'); ?></h4>
                                                </div>
                                                <div class="alert">
                                                    <p><?php echo safeText('No hay transacciones que coincidan con los criterios de búsqueda.'); ?></p>
                                                    <ul style="margin-top: 10px; padding-left: 20px;">
                                                        <li><?php echo safeText('Verifique los datos ingresados'); ?></li>
                                                        <li><?php echo safeText('Confirme la fecha de la transacción'); ?></li>
                                                        <li><?php echo safeText('Verifique la referencia de pago'); ?></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                        <?php else: ?>
                                            <?php foreach ($apiResponse['transaction_list'] as $transaction): ?>
                                                <?php 
                                                $isPayment = in_array(strtolower($transaction['trx_type']), ['p2p_payment', 'payment', 'pago', 'transfer']);
                                                $isVuelto = strtolower($transaction['trx_type']) === 'vuelto';
                                                
                                                if ($isPayment) {
                                                    $statusClass = 'status-success';
                                                    $icon = 'fas fa-check-circle';
                                                    $iconClass = 'success';
                                                    $title = safeText('Pago Verificado');
                                                } elseif ($isVuelto) {
                                                    $statusClass = 'status-info';
                                                    $icon = 'fas fa-exchange-alt';
                                                    $iconClass = 'info';
                                                    $title = safeText('Vuelto/Devolución');
                                                } else {
                                                    $statusClass = 'status-warning';
                                                    $icon = 'fas fa-info-circle';
                                                    $iconClass = 'warning';
                                                    $title = safeText('Transacción Encontrada');
                                                }
                                                ?>
                                                
                                                <div class="status-card <?php echo $statusClass; ?>">
                                                    <div class="response-title">
                                                        <div class="icon-badge <?php echo $iconClass; ?>">
                                                            <i class="<?php echo $icon; ?>"></i>
                                                        </div>
                                                        <div>
                                                            <h4><?php echo $title; ?></h4>
                                                            <small><?php echo safeText('Tipo:'); ?> <?php echo safeText($transaction['trx_type']); ?></small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="transaction-details">
                                                        <div class="detail-item">
                                                            <div class="detail-label"><?php echo safeText('Fecha'); ?></div>
                                                            <div class="detail-value"><?php echo safeText($transaction['trx_date']); ?></div>
                                                        </div>
                                                        
                                                        <div class="detail-item">
                                                            <div class="detail-label"><?php echo safeText('Monto'); ?></div>
                                                            <div class="detail-value amount">
                                                                <i class="fas fa-bolivar-sign"></i> 
                                                                <?php echo number_format($transaction['amount'], 2, ',', '.'); ?> Bs.
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="detail-item">
                                                            <div class="detail-label"><?php echo safeText('Referencia'); ?></div>
                                                            <div class="detail-value reference"><?php echo safeText($transaction['payment_reference']); ?></div>
                                                        </div>
                                                        
                                                        <div class="detail-item">
                                                            <div class="detail-label"><?php echo safeText('Autorización'); ?></div>
                                                            <div class="detail-value"><?php echo safeText($transaction['authorization_code']); ?></div>
                                                        </div>
                                                        
                                                        <div class="detail-item">
                                                            <div class="detail-label"><?php echo safeText('Método de Pago'); ?></div>
                                                            <div class="detail-value"><?php echo safeText($transaction['payment_method']); ?></div>
                                                        </div>
                                                        
                                                        <div class="detail-item">
                                                            <div class="detail-label"><?php echo safeText('Moneda'); ?></div>
                                                            <div class="detail-value"><?php echo strtoupper(safeText($transaction['currency'])); ?></div>
                                                        </div>
                                                        
                                                        <div class="detail-item">
                                                            <div class="detail-label"><?php echo safeText('Banco Destino'); ?></div>
                                                            <div class="detail-value"><?php echo safeText($transaction['destination_bank_id']); ?></div>
                                                        </div>
                                                        
                                                        <div class="detail-item">
                                                            <div class="detail-label"><?php echo safeText('Factura'); ?></div>
                                                            <div class="detail-value"><?php echo safeText($transaction['invoice_number']); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($isPayment): ?>
                                                        <div class="alert status-success" style="margin-top: 15px;">
                                                            <div class="alert-title">
                                                                <i class="fas fa-check-circle"></i>
                                                                <strong><?php echo safeText('¡Pago Confirmado!'); ?></strong>
                                                            </div>
                                                            <p><?php echo safeText('El pago ha sido verificado exitosamente. Puede proceder con la entrega del producto/servicio.'); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                    <?php else: ?>
                                        <div class="status-card status-warning">
                                            <div class="response-title">
                                                <div class="icon-badge warning">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                </div>
                                                <h4><?php echo safeText('Respuesta inesperada del servidor'); ?></h4>
                                            </div>
                                            <button class="debug-toggle" onclick="toggleDebug()">
                                                <i class="fas fa-code"></i> <?php echo safeText('Ver detalles técnicos'); ?>
                                            </button>
                                            <div class="debug-content" id="debugContent">
                                                <pre><?php print_r($apiResponse); ?></pre>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                
                                <!-- Error Responses -->
                                <?php elseif ($httpCode === 400 || $httpCode === 500): ?>
                                    <div class="status-card status-error">
                                        <div class="response-title">
                                            <div class="icon-badge error">
                                                <i class="fas fa-server"></i>
                                            </div>
                                            <h4><?php echo safeText('Error del Servidor'); ?></h4>
                                        </div>
                                        <div class="alert">
                                            <p><?php echo safeText('El servidor ha respondido con un error HTTP'); ?> <?php echo $httpCode; ?>.</p>
                                            <?php if (!empty($rawResponse)): ?>
                                                <button class="debug-toggle" onclick="toggleDebug()">
                                                    <i class="fas fa-code"></i> <?php echo safeText('Ver respuesta completa'); ?>
                                                </button>
                                                <div class="debug-content" id="debugContent">
                                                    <pre><?php echo safeText($rawResponse); ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                
                                <!-- Other HTTP Codes -->
                                <?php else: ?>
                                    <div class="status-card status-warning">
                                        <div class="response-title">
                                            <div class="icon-badge warning">
                                                <i class="fas fa-question-circle"></i>
                                            </div>
                                            <h4><?php echo safeText('Respuesta no procesada'); ?></h4>
                                        </div>
                                        <p><?php echo safeText('Código HTTP:'); ?> <strong><?php echo $httpCode; ?></strong></p>
                                        <?php if (!empty($rawResponse)): ?>
                                            <button class="debug-toggle" onclick="toggleDebug()">
                                                <i class="fas fa-code"></i> <?php echo safeText('Ver respuesta del servidor'); ?>
                                            </button>
                                            <div class="debug-content" id="debugContent">
                                                <pre><?php echo safeText($rawResponse); ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <h4><?php echo safeText('Realice una consulta'); ?></h4>
                                    <p><?php echo safeText('Complete el formulario y haga clic en "Consultar Pago" para verificar una transacción.'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                    <?php else: ?>
                        <!-- Initial State -->
                        <div class="empty-state">
                            <i class="fas fa-search-dollar"></i>
                            <h4><?php echo safeText('Esperando consulta'); ?></h4>
                            <p><?php echo safeText('Complete el formulario para verificar un pago móvil.'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para mostrar/ocultar debug info
        function toggleDebug() {
            const debugContent = document.getElementById('debugContent');
            if (debugContent.style.display === 'block') {
                debugContent.style.display = 'none';
            } else {
                debugContent.style.display = 'block';
            }
        }

        // Mostrar loading al enviar el formulario
        document.getElementById('paymentForm').addEventListener('submit', function() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('resultsContent').style.display = 'none';
            
            // Ocultar empty state si existe
            const emptyState = document.querySelector('.empty-state');
            if (emptyState) {
                emptyState.style.display = 'none';
            }
        });

        // Si hay resultados, mostrar automáticamente
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('resultsContent').style.display = 'block';
            });
        <?php endif; ?>

        // Auto-focus en el primer campo
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                document.querySelector('input[name="customer_id"]').focus();
            <?php endif; ?>
        });
    </script>
</body>
</html>