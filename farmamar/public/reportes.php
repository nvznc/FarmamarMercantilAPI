<?php
// Configurar UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once __DIR__ . '/../includes/pdf_generator.php';
checkSession();

$pdo = require '../config/database.php';

// Inicializar variables
$transacciones = [];
$totalMonto = 0;
$fechaInicio = date('Y-m-d');
$fechaFin = date('Y-m-d');
$fechaOperacionInicio = '';
$fechaOperacionFin = '';
$reporteGenerado = false;

// Reporte diario: si viene ?diario=1, prellenar solo hoy (y opcionalmente auto-enviar en JS)
$autoReporteDiario = !empty($_GET['diario']) && $_SERVER['REQUEST_METHOD'] !== 'POST';
if (!empty($_GET['diario'])) {
    $fechaInicio = date('Y-m-d');
    $fechaFin = date('Y-m-d');
}

// Cargar lista de usuarios (para filtro "usuario que verificó")
$usuarios = [];
try {
    $stmtUsu = $pdo->query("SELECT CodUsua, Descrip FROM SSUSRS ORDER BY Descrip");
    $usuarios = $stmtUsu->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si no existe SSUSRS o falla, se usa solo el usuario actual
}

// Procesar filtros
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
    $fechaFin = $_POST['fecha_fin'] ?? date('Y-m-d');
    $fechaOperacionInicio = trim($_POST['fecha_operacion_inicio'] ?? '');
    $fechaOperacionFin = trim($_POST['fecha_operacion_fin'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $referencia = trim($_POST['referencia'] ?? '');
    $usuarioId = trim($_POST['usuario_id'] ?? '');
    
    // Por defecto: solo datos del usuario logueado. Si eligió otro usuario (y está en la lista), filtrar por ese.
    $filtrarUsuarioId = $_SESSION['user_id'];
    if ($usuarioId !== '' && $usuarioId !== (string)$_SESSION['user_id']) {
        $existe = false;
        foreach ($usuarios as $u) {
            if ((string)$u['CodUsua'] === $usuarioId) {
                $existe = true;
                break;
            }
        }
        if ($existe) {
            $filtrarUsuarioId = $usuarioId;
        }
    }
    
    // Construir consulta
    $sql = "SELECT 
                id,
                fecha_verificacion,
                fecha_transaccion,
                monto,
                moneda,
                referencia,
                CONCAT(prefijo_cedula, '-', cedula_cliente) as cedula_completa,
                telefono,
                autorizacion,
                metodo_pago,
                banco_destino,
                estado
            FROM transacciones_verificadas 
            WHERE usuario_id = :usuario_id 
            AND DATE(fecha_verificacion) BETWEEN :fecha_inicio AND :fecha_fin";
    
    $params = [
        ':usuario_id' => $filtrarUsuarioId,
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin
    ];
    
    // Filtro por fecha de operación (fecha_transaccion)
    if ($fechaOperacionInicio !== '') {
        $sql .= " AND DATE(fecha_transaccion) >= :fecha_operacion_inicio";
        $params[':fecha_operacion_inicio'] = $fechaOperacionInicio;
    }
    if ($fechaOperacionFin !== '') {
        $sql .= " AND DATE(fecha_transaccion) <= :fecha_operacion_fin";
        $params[':fecha_operacion_fin'] = $fechaOperacionFin;
    }
    
    if ($cedula !== '') {
        $sql .= " AND cedula_cliente LIKE :cedula";
        $params[':cedula'] = "%$cedula%";
    }
    
    if ($referencia !== '') {
        $sql .= " AND referencia LIKE :referencia";
        $params[':referencia'] = "%$referencia%";
    }
    
    $sql .= " ORDER BY fecha_verificacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Nombre del usuario del reporte (para resumen y PDF)
    $nombreUsuarioReporte = $_SESSION['user_name'];
    if ($filtrarUsuarioId !== $_SESSION['user_id']) {
        foreach ($usuarios as $u) {
            if ((string)$u['CodUsua'] === (string)$filtrarUsuarioId) {
                $nombreUsuarioReporte = $u['Descrip'];
                break;
            }
        }
    }
    
    // Calcular totales
    foreach ($transacciones as $trans) {
        $totalMonto += $trans['monto'];
    }
    
    $reporteGenerado = true;
    
    // Registrar en reportes_usuarios (historial de reportes generados)
    try {
        $parametrosBusqueda = json_encode([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'fecha_operacion_inicio' => $fechaOperacionInicio,
            'fecha_operacion_fin' => $fechaOperacionFin,
            'referencia' => $referencia,
            'cedula' => $cedula,
            'usuario_filtro_id' => $filtrarUsuarioId
        ], JSON_UNESCAPED_UNICODE);
        $stmtReg = $pdo->prepare("INSERT INTO reportes_usuarios 
            (usuario_id, fecha_inicio, fecha_fin, total_transacciones, monto_total, parametros_busqueda) 
            VALUES (:usuario_id, :fecha_inicio, :fecha_fin, :total_transacciones, :monto_total, :parametros_busqueda)");
        $stmtReg->execute([
            ':usuario_id' => $_SESSION['user_id'],
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin' => $fechaFin,
            ':total_transacciones' => count($transacciones),
            ':monto_total' => $totalMonto,
            ':parametros_busqueda' => $parametrosBusqueda
        ]);
    } catch (PDOException $e) {
        // No fallar la página si la tabla no existe o hay error de FK
        error_log("Reportes: no se pudo registrar en reportes_usuarios: " . $e->getMessage());
    }
    
    // Generar PDF si se solicita
    if (isset($_POST['generar_pdf'])) {
        require_once '../includes/pdf_generator.php';
        generarPDFReporte($transacciones, $nombreUsuarioReporte, $fechaInicio, $fechaFin, $totalMonto);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Transacciones - Farmamar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Mantén los mismos estilos que dashboard.php o usa estos simplificados */
        :root {
            --primary: #004a99;
            --secondary: #28a745;
            --light: #f8f9fa;
            --dark: #343a40;
            --border-radius: 10px;
            --box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        
        .header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }
        
        .nav {
            background: var(--light);
            padding: 10px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav a {
            color: var(--dark);
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
        }
        
        .content {
            padding: 30px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #003366;
        }
        
        .btn-success {
            background: var(--secondary);
            color: white;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .summary-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .summary-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            min-width: 150px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-chart-bar"></i> Reportes de Transacciones</h1>
                <p>Farmamar - Sistema de Verificación C2P</p>
            </div>
            <div class="user-info">
                <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong><br>
                <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a> |
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </div>
        
        <div class="nav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a>
            <a href="reportes.php" style="color: var(--primary);"><i class="fas fa-file-alt"></i> Reportes</a>
        </div>
        
        <div class="content">
            <!-- Formulario de filtros -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-filter"></i> Filtros del Reporte
                </div>
                <div class="card-body">
                    <form method="POST">
                        <p style="margin-bottom: 15px; color: #6c757d; font-size: 14px;">
                            <i class="fas fa-info-circle"></i> Filtre por <strong>fecha de verificación</strong>, <strong>fecha de operación</strong>, <strong>número de referencia</strong> o <strong>usuario que verificó</strong> para generar su reporte diario o por período.
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
                            <div class="form-group">
                                <label for="fecha_inicio"><i class="fas fa-calendar-check"></i> Fecha verificación desde</label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" 
                                       value="<?php echo htmlspecialchars($fechaInicio); ?>" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="fecha_fin"><i class="fas fa-calendar-check"></i> Fecha verificación hasta</label>
                                <input type="date" id="fecha_fin" name="fecha_fin" 
                                       value="<?php echo htmlspecialchars($fechaFin); ?>" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="fecha_operacion_inicio"><i class="fas fa-calendar-alt"></i> Fecha operación desde (opcional)</label>
                                <input type="date" id="fecha_operacion_inicio" name="fecha_operacion_inicio" 
                                       value="<?php echo htmlspecialchars($fechaOperacionInicio); ?>" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="fecha_operacion_fin"><i class="fas fa-calendar-alt"></i> Fecha operación hasta (opcional)</label>
                                <input type="date" id="fecha_operacion_fin" name="fecha_operacion_fin" 
                                       value="<?php echo htmlspecialchars($fechaOperacionFin); ?>" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="referencia"><i class="fas fa-hashtag"></i> Número de referencia (opcional)</label>
                                <input type="text" id="referencia" name="referencia" 
                                       value="<?php echo htmlspecialchars($_POST['referencia'] ?? ''); ?>"
                                       placeholder="Ej: 123456" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="usuario_id"><i class="fas fa-user-check"></i> Usuario que verificó</label>
                                <select id="usuario_id" name="usuario_id" class="form-control">
                                    <option value="">Mi usuario (<?php echo htmlspecialchars($_SESSION['user_name']); ?>)</option>
                                    <?php foreach ($usuarios as $u): 
                                        if ((string)$u['CodUsua'] === (string)$_SESSION['user_id']) continue;
                                        $sel = (isset($_POST['usuario_id']) && $_POST['usuario_id'] === (string)$u['CodUsua']) ? ' selected' : '';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($u['CodUsua']); ?>"<?php echo $sel; ?>>
                                        <?php echo htmlspecialchars($u['Descrip']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="cedula"><i class="fas fa-id-card"></i> Cédula (opcional)</label>
                                <input type="text" id="cedula" name="cedula" 
                                       value="<?php echo htmlspecialchars($_POST['cedula'] ?? ''); ?>"
                                       placeholder="Buscar por cédula" class="form-control">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
                            <button type="submit" name="generar_reporte" class="btn btn-primary">
                                <i class="fas fa-search"></i> Generar Reporte
                            </button>
                            <a href="reportes.php?diario=1" class="btn btn-primary" style="text-decoration: none; display: inline-flex; align-items: center;">
                                <i class="fas fa-calendar-day"></i> Reporte de hoy
                            </a>
                            <?php if ($reporteGenerado && !empty($transacciones)): ?>
                            <button type="submit" name="generar_pdf" class="btn btn-success">
                                <i class="fas fa-file-pdf"></i> Exportar a PDF
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($reporteGenerado): ?>
                <!-- Resumen del reporte -->
                <div class="summary-card">
                    <h3>Resumen del Reporte</h3>
                    <?php if (isset($nombreUsuarioReporte) && $nombreUsuarioReporte !== $_SESSION['user_name']): ?>
                    <p style="margin: 0 0 12px 0; opacity: 0.95;"><i class="fas fa-user-check"></i> Usuario que verificó: <strong><?php echo htmlspecialchars($nombreUsuarioReporte); ?></strong></p>
                    <?php endif; ?>
                    <div class="summary-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo count($transacciones); ?></div>
                            <div class="stat-label">Transacciones</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($totalMonto, 2, ',', '.'); ?> Bs.</div>
                            <div class="stat-label">Monto Total</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $fechaInicio; ?></div>
                            <div class="stat-label">Verif. desde</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $fechaFin; ?></div>
                            <div class="stat-label">Verif. hasta</div>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de transacciones -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-list"></i> Transacciones Encontradas: <?php echo count($transacciones); ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transacciones)): ?>
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 48px; color: #6c757d; margin-bottom: 20px;"></i>
                                <h3>No se encontraron transacciones</h3>
                                <p>No hay transacciones que coincidan con los criterios de búsqueda.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Fecha Transacción</th>
                                            <th>Fecha Verificación</th>
                                            <th>Monto</th>
                                            <th>Referencia</th>
                                            <th>Cédula</th>
                                            <th>Teléfono</th>
                                            <th>Autorización</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transacciones as $trans): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($trans['fecha_transaccion']); ?></td>
                                            <td><?php echo htmlspecialchars($trans['fecha_verificacion']); ?></td>
                                            <td style="font-weight: bold;">
                                                <?php echo number_format($trans['monto'], 2, ',', '.'); ?> Bs.
                                            </td>
                                            <td>
                                                <span style="font-family: monospace;">
                                                    <?php echo htmlspecialchars($trans['referencia']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($trans['cedula_completa']); ?></td>
                                            <td><?php echo htmlspecialchars($trans['telefono']); ?></td>
                                            <td><?php echo htmlspecialchars($trans['autorizacion']); ?></td>
                                            <td>
                                                <?php if ($trans['estado'] == 'VERIFICADO'): ?>
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-check-circle"></i> Verificado
                                                    </span>
                                                <?php elseif ($trans['estado'] == 'PENDIENTE'): ?>
                                                    <span class="badge badge-warning">
                                                        <i class="fas fa-clock"></i> Pendiente
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">
                                                        <i class="fas fa-times-circle"></i> Rechazado
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInicio = document.getElementById('fecha_inicio');
            const fechaFin = document.getElementById('fecha_fin');
            const today = new Date().toISOString().split('T')[0];
            if (fechaInicio) fechaInicio.max = today;
            if (fechaFin) fechaFin.max = today;
            if (fechaInicio) fechaInicio.addEventListener('change', function() { fechaFin.min = this.value; });
            if (fechaFin) fechaFin.addEventListener('change', function() { fechaInicio.max = this.value; });

            // Reporte de hoy: auto-enviar el formulario si se entró con ?diario=1 (solo primera carga)
            <?php if (!empty($autoReporteDiario)): ?>
            (function() {
                var form = document.querySelector('form[method="POST"]');
                if (form && fechaInicio && fechaFin) {
                    fechaInicio.value = today;
                    fechaFin.value = today;
                    form.submit();
                }
            })();
            <?php endif; ?>
        });
    </script>
</body>
</html>