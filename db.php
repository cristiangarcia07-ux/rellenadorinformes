<?php
declare(strict_types=1);

$configFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

if (!is_file($configFile)) {
    header('Location: setup.php');
    exit;
}

$config = require $configFile;

function report_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $config;

    $host = $config['host'] ?? '127.0.0.1';
    $name = $config['name'] ?? 'rellenadorinforme';
    $user = $config['user'] ?? 'root';
    $pass = $config['pass'] ?? '';

    $server = new PDO(
        "mysql:host={$host};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    ensure_report_schema($pdo);

    return $pdo;
}

function ensure_report_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS informacion_semana (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        hoja1 VARCHAR(10) DEFAULT NULL,
        hoja2 VARCHAR(10) DEFAULT NULL,
        diainicio INT DEFAULT NULL,
        diafin INT DEFAULT NULL,
        mes VARCHAR(60) DEFAULT NULL,
        ano VARCHAR(4) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS informacion_del_centro_docente (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nombre_y_apellidos VARCHAR(255) DEFAULT NULL,
        nombre_alumno VARCHAR(255) DEFAULT NULL,
        centro_docente VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS centro_trabajo (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nombre_centro_trabajo VARCHAR(255) DEFAULT NULL,
        tutor_trabajo VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ciclos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(255) DEFAULT NULL,
        grado VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dias (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(260) DEFAULT NULL,
        actividades VARCHAR(260) DEFAULT NULL,
        tiempo VARCHAR(260) DEFAULT NULL,
        observaciones VARCHAR(260) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS relacion_semana_centro_trabajo (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        id_semana INT UNSIGNED NOT NULL,
        id_centro_docente INT UNSIGNED NOT NULL,
        id_ciclo INT UNSIGNED NOT NULL,
        id_centro_trabajo INT UNSIGNED NOT NULL,
        firma_alumno VARCHAR(255) DEFAULT NULL,
        firma_profe VARCHAR(255) DEFAULT NULL,
        firma_tutor VARCHAR(255) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_rel_semana (id_semana),
        KEY idx_rel_centro_docente (id_centro_docente),
        KEY idx_rel_ciclo (id_ciclo),
        KEY idx_rel_centro_trabajo (id_centro_trabajo),
        CONSTRAINT fk_rel_semana
            FOREIGN KEY (id_semana) REFERENCES informacion_semana(id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_rel_centro_docente
            FOREIGN KEY (id_centro_docente) REFERENCES informacion_del_centro_docente(id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_rel_ciclo
            FOREIGN KEY (id_ciclo) REFERENCES ciclos(id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_rel_centro_trabajo
            FOREIGN KEY (id_centro_trabajo) REFERENCES centro_trabajo(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS relacion_semana_dia (
        id_relacion INT UNSIGNED NOT NULL,
        id_dia INT UNSIGNED NOT NULL,
        posicion TINYINT UNSIGNED NOT NULL,
        PRIMARY KEY (id_relacion, posicion),
        KEY idx_rel_dia (id_dia),
        CONSTRAINT fk_relacion_dia_relacion
            FOREIGN KEY (id_relacion) REFERENCES relacion_semana_centro_trabajo(id)
            ON UPDATE CASCADE ON DELETE CASCADE,
        CONSTRAINT fk_relacion_dia_dia
            FOREIGN KEY (id_dia) REFERENCES dias(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function insert_report_row(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $pdo->lastInsertId();
}

function save_report(PDO $pdo, array $values, ?int $id = null): int
{
    $pdo->beginTransaction();

    try {
        if ($id !== null) {
            delete_report($pdo, $id, false);
        }

        $semanaId = insert_report_row(
            $pdo,
            'INSERT INTO informacion_semana (hoja1, hoja2, diainicio, diafin, mes, ano) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $values['hoja1'] ?: null,
                $values['hoja2'] ?: null,
                $values['dia1'] !== '' ? (int) $values['dia1'] : null,
                $values['dia2'] !== '' ? (int) $values['dia2'] : null,
                $values['mes'] ?: null,
                $values['ano'] ?: null,
            ]
        );

        $docenteId = insert_report_row(
            $pdo,
            'INSERT INTO informacion_del_centro_docente (nombre_y_apellidos, nombre_alumno, centro_docente) VALUES (?, ?, ?)',
            [$values['profesor'] ?: null, $values['alumno'] ?: null, $values['centro_docente'] ?: null]
        );

        $trabajoId = insert_report_row(
            $pdo,
            'INSERT INTO centro_trabajo (nombre_centro_trabajo, tutor_trabajo) VALUES (?, ?)',
            [$values['centro_trabajo'] ?: null, $values['tutor'] ?: null]
        );

        $cicloId = insert_report_row(
            $pdo,
            'INSERT INTO ciclos (nombre, grado) VALUES (?, ?)',
            [$values['ciclo'] ?: null, $values['grado'] ?: null]
        );

        $relacionId = insert_report_row(
            $pdo,
            'INSERT INTO relacion_semana_centro_trabajo (id_semana, id_centro_docente, id_ciclo, id_centro_trabajo, firma_alumno, firma_profe, firma_tutor) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $semanaId,
                $docenteId,
                $cicloId,
                $trabajoId,
                $values['firma_alumno'] ?: null,
                $values['firma_profe'] ?: null,
                $values['firma_tutor'] ?: null,
            ]
        );

        for ($index = 0; $index < 5; $index++) {
            $diaId = insert_report_row(
                $pdo,
                'INSERT INTO dias (nombre, actividades, tiempo, observaciones) VALUES (?, ?, ?, ?)',
                [
                    'Dia ' . ($index + 1),
                    $values['actividad_' . $index] ?: null,
                    $values['tiempo_' . $index] ?: null,
                    $values['observaciones_' . $index] ?: null,
                ]
            );

            $stmt = $pdo->prepare('INSERT INTO relacion_semana_dia (id_relacion, id_dia, posicion) VALUES (?, ?, ?)');
            $stmt->execute([$relacionId, $diaId, $index]);
        }

        $pdo->commit();
        return $relacionId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function delete_report(PDO $pdo, int $id, bool $wrapTransaction = true): void
{
    if ($wrapTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT id_dia FROM relacion_semana_dia WHERE id_relacion = ?');
        $stmt->execute([$id]);
        $diaIds = array_column($stmt->fetchAll(), 'id_dia');

        $stmt = $pdo->prepare('SELECT id_semana, id_centro_docente, id_ciclo, id_centro_trabajo FROM relacion_semana_centro_trabajo WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            if ($wrapTransaction) {
                $pdo->commit();
            }
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM relacion_semana_centro_trabajo WHERE id = ?');
        $stmt->execute([$id]);

        foreach ([
            ['informacion_semana', $row['id_semana']],
            ['informacion_del_centro_docente', $row['id_centro_docente']],
            ['ciclos', $row['id_ciclo']],
            ['centro_trabajo', $row['id_centro_trabajo']],
        ] as [$table, $rowId]) {
            $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$rowId]);
        }

        foreach ($diaIds as $diaId) {
            $pdo->prepare('DELETE FROM dias WHERE id = ?')->execute([$diaId]);
        }

        if ($wrapTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($wrapTransaction) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function fetch_saved_reports(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT
        r.id,
        r.updated_at,
        s.diainicio,
        s.diafin,
        s.mes,
        s.ano,
        d.nombre_alumno
        FROM relacion_semana_centro_trabajo r
        INNER JOIN informacion_semana s ON s.id = r.id_semana
        INNER JOIN informacion_del_centro_docente d ON d.id = r.id_centro_docente
        ORDER BY r.updated_at DESC, r.id DESC");

    return $stmt->fetchAll();
}

function fetch_report_values(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("SELECT
        r.id,
        r.firma_alumno,
        r.firma_profe,
        r.firma_tutor,
        s.hoja1,
        s.hoja2,
        s.diainicio,
        s.diafin,
        s.mes,
        s.ano,
        d.centro_docente,
        d.nombre_y_apellidos,
        d.nombre_alumno,
        t.nombre_centro_trabajo,
        t.tutor_trabajo,
        c.nombre AS ciclo,
        c.grado
        FROM relacion_semana_centro_trabajo r
        INNER JOIN informacion_semana s ON s.id = r.id_semana
        INNER JOIN informacion_del_centro_docente d ON d.id = r.id_centro_docente
        INNER JOIN centro_trabajo t ON t.id = r.id_centro_trabajo
        INNER JOIN ciclos c ON c.id = r.id_ciclo
        WHERE r.id = ?
        LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        return [];
    }

    $values = [
        'hoja1' => (string) ($row['hoja1'] ?? ''),
        'hoja2' => (string) ($row['hoja2'] ?? ''),
        'dia1' => (string) ($row['diainicio'] ?? ''),
        'dia2' => (string) ($row['diafin'] ?? ''),
        'mes' => (string) ($row['mes'] ?? ''),
        'ano' => (string) ($row['ano'] ?? ''),
        'centro_docente' => (string) ($row['centro_docente'] ?? ''),
        'centro_trabajo' => (string) ($row['nombre_centro_trabajo'] ?? ''),
        'tutor' => (string) ($row['tutor_trabajo'] ?? ''),
        'profesor' => (string) ($row['nombre_y_apellidos'] ?? ''),
        'alumno' => (string) ($row['nombre_alumno'] ?? ''),
        'ciclo' => (string) ($row['ciclo'] ?? ''),
        'grado' => (string) ($row['grado'] ?? ''),
        'firma_alumno' => (string) ($row['firma_alumno'] ?? ''),
        'firma_profe' => (string) ($row['firma_profe'] ?? ''),
        'firma_tutor' => (string) ($row['firma_tutor'] ?? ''),
    ];

    $stmt = $pdo->prepare("SELECT d.actividades, d.tiempo, d.observaciones, rd.posicion
        FROM relacion_semana_dia rd
        INNER JOIN dias d ON d.id = rd.id_dia
        WHERE rd.id_relacion = ?
        ORDER BY rd.posicion ASC");
    $stmt->execute([$id]);

    foreach ($stmt->fetchAll() as $dia) {
        $position = (int) $dia['posicion'];
        $values['actividad_' . $position] = (string) ($dia['actividades'] ?? '');
        $values['tiempo_' . $position] = (string) ($dia['tiempo'] ?? '');
        $values['observaciones_' . $position] = (string) ($dia['observaciones'] ?? '');
    }

    return $values;
}
