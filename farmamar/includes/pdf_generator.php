<?php
function generarPDFReporte($transacciones, $usuario, $fechaInicio, $fechaFin, $totalMonto) {
    // Cargar autoload de Composer - RUTA CORRECTA para /farmamar/
    require_once __DIR__ . '/../vendor/autoload.php';
    
    try {
        // Crear nueva instancia de PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configuración del documento
        $pdf->SetCreator('Farmamar C2P');
        $pdf->SetAuthor('Sistema Farmamar');
        $pdf->SetTitle('Reporte de Transacciones');
        $pdf->SetSubject('Reporte de verificaciones C2P');
        
        // Eliminar header/footer por defecto
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Agregar página
        $pdf->AddPage();
        
        // Logo y encabezado
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'FARMAMAR - Reporte de Transacciones', 0, 1, 'C');
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Usuario: ' . $usuario, 0, 1);
        $pdf->Cell(0, 6, 'Periodo: ' . $fechaInicio . ' al ' . $fechaFin, 0, 1);
        $pdf->Cell(0, 6, 'Fecha de generación: ' . date('d/m/Y H:i:s'), 0, 1);
        $pdf->Ln(10);
        
        // Resumen
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'RESUMEN', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Total de transacciones: ' . count($transacciones), 0, 1);
        $pdf->Cell(0, 6, 'Monto total: ' . number_format($totalMonto, 2, ',', '.') . ' Bs.', 0, 1);
        $pdf->Ln(10);
        
        // Tabla de transacciones
        if (!empty($transacciones)) {
            $pdf->SetFont('helvetica', 'B', 10);
            
            // Encabezados de tabla
            $header = array('Fecha Trans.', 'Fecha Verif.', 'Monto', 'Referencia', 'Cédula', 'Estado');
            $widths = array(25, 25, 25, 30, 25, 25);
            
            // Imprimir encabezados
            foreach ($header as $i => $col) {
                $pdf->Cell($widths[$i], 7, $col, 1, 0, 'C');
            }
            $pdf->Ln();
            
            // Datos de la tabla
            $pdf->SetFont('helvetica', '', 8);
            foreach ($transacciones as $trans) {
                $pdf->Cell($widths[0], 6, $trans['fecha_transaccion'], 1);
                $pdf->Cell($widths[1], 6, substr($trans['fecha_verificacion'], 0, 10), 1);
                $pdf->Cell($widths[2], 6, number_format($trans['monto'], 2, ',', '.'), 1, 0, 'R');
                $pdf->Cell($widths[3], 6, $trans['referencia'], 1);
                $pdf->Cell($widths[4], 6, $trans['cedula_completa'] ?? '', 1);
                
                // Estado con color
                $estado = $trans['estado'];
                if ($estado == 'VERIFICADO') {
                    $pdf->SetTextColor(0, 128, 0);
                    $pdf->Cell($widths[5], 6, 'Verificado', 1, 0, 'C');
                } elseif ($estado == 'PENDIENTE') {
                    $pdf->SetTextColor(255, 165, 0);
                    $pdf->Cell($widths[5], 6, 'Pendiente', 1, 0, 'C');
                } else {
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->Cell($widths[5], 6, 'Rechazado', 1, 0, 'C');
                }
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Ln();
            }
        }
        
        // Pie de página
        $pdf->SetY(-20);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Reporte generado automáticamente por Sistema Farmamar C2P', 0, 0, 'C');
        
        // Salida del PDF
        $filename = 'reporte_' . date('Ymd_His') . '.pdf';
        $pdf->Output($filename, 'D');
        
    } catch (Exception $e) {
        // Fallback a HTML si hay error con TCPDF
        generarHTMLReporte($transacciones, $usuario, $fechaInicio, $fechaFin, $totalMonto);
    }
}

// Función de respaldo en HTML
function generarHTMLReporte($transacciones, $usuario, $fechaInicio, $fechaFin, $totalMonto) {
    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Reporte de Transacciones - ' . htmlspecialchars($usuario) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .total { font-weight: bold; font-size: 16px; margin-top: 20px; }
            @media print {
                .no-print { display: none; }
                body { margin: 0; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>FARMAMAR - Reporte de Transacciones</h1>
            <p><strong>Usuario:</strong> ' . htmlspecialchars($usuario) . '</p>
            <p><strong>Periodo:</strong> ' . htmlspecialchars($fechaInicio) . ' al ' . htmlspecialchars($fechaFin) . '</p>
            <p><strong>Generado:</strong> ' . date('d/m/Y H:i:s') . '</p>
        </div>
        
        <div class="total">
            <p>Total de transacciones: ' . count($transacciones) . '</p>
            <p>Monto total: ' . number_format($totalMonto, 2, ',', '.') . ' Bs.</p>
        </div>';
        
        if (!empty($transacciones)) {
            echo '<table>
                <thead>
                    <tr>
                        <th>Fecha Transacción</th>
                        <th>Fecha Verificación</th>
                        <th>Monto</th>
                        <th>Referencia</th>
                        <th>Cédula</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($transacciones as $trans) {
                $estadoColor = '';
                if ($trans['estado'] == 'VERIFICADO') $estadoColor = 'color: green;';
                elseif ($trans['estado'] == 'PENDIENTE') $estadoColor = 'color: orange;';
                else $estadoColor = 'color: red;';
                
                echo '<tr>
                    <td>' . htmlspecialchars($trans['fecha_transaccion']) . '</td>
                    <td>' . htmlspecialchars(substr($trans['fecha_verificacion'], 0, 10)) . '</td>
                    <td>' . number_format($trans['monto'], 2, ',', '.') . ' Bs.</td>
                    <td>' . htmlspecialchars($trans['referencia']) . '</td>
                    <td>' . htmlspecialchars($trans['cedula_completa'] ?? '') . '</td>
                    <td style="' . $estadoColor . '">' . htmlspecialchars($trans['estado']) . '</td>
                </tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p style="text-align: center; font-style: italic;">No hay transacciones para mostrar</p>';
        }
        
        echo '<div class="no-print" style="margin-top: 30px; text-align: center;">
            <button onclick="window.print()">Imprimir Reporte</button>
            <button onclick="window.close()">Cerrar</button>
        </div>
        <script>
            window.onload = function() {
                // Auto-imprimir si está en una ventana nueva
                if (window.opener) {
                    window.print();
                }
            }
        </script>
    </body>
    </html>';
}
?>