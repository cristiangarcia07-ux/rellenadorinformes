<?php
declare(strict_types=1);

$step = 'form';
$message = '';
$messageType = '';
$config = [];

$configFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

if (is_file($configFile)) {
    $config = require $configFile;
    if (!empty($config['host']) && !empty($config['name'])) {
        $step = 'already_configured';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step !== 'already_configured') {
    $host = trim((string)($_POST['host'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['pass'] ?? '');

    if (!$host || !$name || !$user) {
        $message = 'Por favor, completa todos los campos obligatorios.';
        $messageType = 'error';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$host};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $pdo = new PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            $schema = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'schema.sql');
            if ($schema === false) {
                throw new RuntimeException('No se pudo leer el archivo schema.sql');
            }

            $pdo->exec($schema);

            $configContent = '<?php' . PHP_EOL . 'return [' . PHP_EOL;
            $configContent .= '    \'host\' => ' . var_export($host, true) . ',' . PHP_EOL;
            $configContent .= '    \'name\' => ' . var_export($name, true) . ',' . PHP_EOL;
            $configContent .= '    \'user\' => ' . var_export($user, true) . ',' . PHP_EOL;
            $configContent .= '    \'pass\' => ' . var_export($pass, true) . ',' . PHP_EOL;
            $configContent .= '];' . PHP_EOL;

            file_put_contents($configFile, $configContent);

            $step = 'success';
        } catch (Throwable $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuracion - Rellenador de ficha semanal</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eef2f6;
            color: #17202a;
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            background: #ffffff;
            border-radius: 12px;
            padding: 32px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        h1 { margin: 0 0 8px; font-size: 22px; }
        p { margin: 0 0 18px; color: #526173; }
        label {
            display: grid;
            gap: 5px;
            margin-bottom: 14px;
            color: #526173;
            font-size: 13px;
            font-weight: 700;
        }
        input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 9px 10px;
            color: #17202a;
            font: inherit;
            font-weight: 400;
        }
        button {
            border: 0;
            border-radius: 8px;
            width: 100%;
            min-height: 42px;
            background: #0f766e;
            color: #ffffff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            margin-top: 8px;
        }
        .error {
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #991b1b;
            border-radius: 8px;
            padding: 10px;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .success {
            border: 1px solid #99f6e4;
            background: #ecfdf5;
            color: #0f766e;
            border-radius: 8px;
            padding: 10px;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .info {
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1e40af;
            border-radius: 8px;
            padding: 10px;
            font-weight: 700;
            margin-bottom: 14px;
        }
        a { color: #0f766e; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($step === 'already_configured'): ?>
            <h1>Ya configurado</h1>
            <div class="info">La base de datos ya esta configurada.</div>
            <p><a href="index.php">Ir a la aplicacion</a></p>
        <?php elseif ($step === 'success'): ?>
            <h1>Configuracion completada</h1>
            <div class="success">La base de datos se ha configurado correctamente.</div>
            <p><a href="index.php">Ir a la aplicacion</a></p>
        <?php else: ?>
            <h1>Configuracion inicial</h1>
            <p>Configura la conexion a la base de datos MySQL.</p>
            <?php if ($message): ?>
                <div class="<?= h($messageType) ?>"><?= h($message) ?></div>
            <?php endif; ?>
            <form method="post">
                <label>Host
                    <input name="host" value="<?= h($host ?? '127.0.0.1') ?>" required>
                </label>
                <label>Base de datos
                    <input name="name" value="<?= h($name ?? 'rellenadorinforme') ?>" required>
                    <small>Se creara si no existe.</small>
                </label>
                <label>Usuario
                    <input name="user" value="<?= h($user ?? 'root') ?>" required>
                </label>
                <label>Contrasena
                    <input name="pass" type="password" value="<?= h($pass ?? '') ?>">
                </label>
                <button type="submit">Configurar y crear base de datos</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
