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
    $schema = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'schema.sql');
    if ($schema === false) {
        throw new RuntimeException('No se pudo leer schema.sql');
    }
    $pdo->exec($schema);
    
    // Add missing columns to informacion_semana if they don't exist
    $columns_to_check = [
        'firma_alumno' => "ALTER TABLE informacion_semana ADD COLUMN firma_alumno VARCHAR(255) DEFAULT NULL",
        'firma_profe' => "ALTER TABLE informacion_semana ADD COLUMN firma_profe VARCHAR(255) DEFAULT NULL",
        'firma_tutor' => "ALTER TABLE informacion_semana ADD COLUMN firma_tutor VARCHAR(255) DEFAULT NULL",
        'created_at' => "ALTER TABLE informacion_semana ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "ALTER TABLE informacion_semana ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ];
    
    foreach ($columns_to_check as $column => $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Column probably already exists
        }
    }
    
    // Add missing columns to dias if they don't exist
    $dias_columns = [
        'id_semana' => "ALTER TABLE dias ADD COLUMN id_semana INT UNSIGNED DEFAULT NULL",
        'posicion' => "ALTER TABLE dias ADD COLUMN posicion TINYINT UNSIGNED DEFAULT NULL",
    ];
    
    foreach ($dias_columns as $column => $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Column probably already exists
        }
    }
    
    // Add foreign key to dias if it doesn't exist (MySQL doesn't support IF NOT EXISTS for foreign keys)
    try {
        $pdo->exec("ALTER TABLE dias ADD CONSTRAINT fk_dias_semana FOREIGN KEY(id_semana) REFERENCES informacion_semana(id) ON UPDATE CASCADE ON DELETE CASCADE");
    } catch (Exception $e) {
        // Constraint probably already exists
    }
}

function insert_report_row(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $pdo->lastInsertId();
}

function save_report(PDO $pdo, array $values, ?int $id = null, ?int $existing_docente_id = null, ?int $existing_trabajo_id = null, ?int $existing_ciclo_id = null): int
{
    $pdo->beginTransaction();

    try {
        if ($id !== null) {
            $stmt = $pdo->prepare('SELECT id FROM informacion_semana WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();

            if ($existing) {
                $semanaId = $id;
                $stmt = $pdo->prepare('UPDATE informacion_semana SET hoja1 = ?, hoja2 = ?, diainicio = ?, diafin = ?, mes = ?, ano = ?, firma_alumno = ?, firma_profe = ?, firma_tutor = ? WHERE id = ?');
                $stmt->execute([
                    $values['hoja1'] ?: null,
                    $values['hoja2'] ?: null,
                    $values['dia1'] !== '' ? (int) $values['dia1'] : null,
                    $values['dia2'] !== '' ? (int) $values['dia2'] : null,
                    $values['mes'] ?: null,
                    $values['ano'] ?: null,
                    $values['firma_alumno'] ?: null,
                    $values['firma_profe'] ?: null,
                    $values['firma_tutor'] ?: null,
                    $semanaId,
                ]);

                $stmt = $pdo->prepare('DELETE FROM centroeduc_semana WHERE id_semana = ?');
                $stmt->execute([$semanaId]);
                $stmt = $pdo->prepare('DELETE FROM centrotrabajo_semana WHERE id_semana = ?');
                $stmt->execute([$semanaId]);
                $stmt = $pdo->prepare('DELETE FROM ciclos_semana WHERE id_semana = ?');
                $stmt->execute([$semanaId]);
                $stmt = $pdo->prepare('DELETE FROM dias WHERE id_semana = ?');
                $stmt->execute([$semanaId]);
            } else {
                $id = null;
            }
        }

        if ($id === null) {
            $semanaId = insert_report_row(
                $pdo,
                'INSERT INTO informacion_semana (hoja1, hoja2, diainicio, diafin, mes, ano, firma_alumno, firma_profe, firma_tutor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $values['hoja1'] ?: null,
                    $values['hoja2'] ?: null,
                    $values['dia1'] !== '' ? (int) $values['dia1'] : null,
                    $values['dia2'] !== '' ? (int) $values['dia2'] : null,
                    $values['mes'] ?: null,
                    $values['ano'] ?: null,
                    $values['firma_alumno'] ?: null,
                    $values['firma_profe'] ?: null,
                    $values['firma_tutor'] ?: null,
                ]
            );
        }

        $docenteId = $existing_docente_id ?? insert_report_row(
            $pdo,
            'INSERT INTO informacion_del_centro_docente (nombre_y_apellidos, nombre_alumno, centro_docente) VALUES (?, ?, ?)',
            [$values['profesor'] ?: null, $values['alumno'] ?: null, $values['centro_docente'] ?: null]
        );

        $trabajoId = $existing_trabajo_id ?? insert_report_row(
            $pdo,
            'INSERT INTO centro_trabajo (nombre_centro_trabajo, tutor_trabajo) VALUES (?, ?)',
            [$values['centro_trabajo'] ?: null, $values['tutor'] ?: null]
        );

        $cicloId = $existing_ciclo_id ?? insert_report_row(
            $pdo,
            'INSERT INTO ciclos (nombre, grado) VALUES (?, ?)',
            [$values['ciclo'] ?: null, $values['grado'] ?: null]
        );

        $stmt = $pdo->prepare('INSERT INTO centroeduc_semana (id_semana, id_centro_docente) VALUES (?, ?)');
        $stmt->execute([$semanaId, $docenteId]);

        $stmt = $pdo->prepare('INSERT INTO centrotrabajo_semana (id_centro_trabajo, id_semana) VALUES (?, ?)');
        $stmt->execute([$trabajoId, $semanaId]);

        $stmt = $pdo->prepare('INSERT INTO ciclos_semana (id_semana, id_ciclo) VALUES (?, ?)');
        $stmt->execute([$semanaId, $cicloId]);

        for ($index = 0; $index < 5; $index++) {
            insert_report_row(
                $pdo,
                'INSERT INTO dias (nombre, actividades, tiempo, observaciones, id_semana, posicion) VALUES (?, ?, ?, ?, ?, ?)',
                [
                    'Dia ' . ($index + 1),
                    $values['actividad_' . $index] ?: null,
                    $values['tiempo_' . $index] ?: null,
                    $values['observaciones_' . $index] ?: null,
                    $semanaId,
                    $index,
                ]
            );
        }

        $pdo->commit();
        return $semanaId;
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
        $stmt = $pdo->prepare('SELECT id_centro_docente, id_centro_trabajo, id_ciclo FROM centroeduc_semana ces
            INNER JOIN centrotrabajo_semana cts ON cts.id_semana = ces.id_semana
            INNER JOIN ciclos_semana cs ON cs.id_semana = ces.id_semana
            WHERE ces.id_semana = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            if ($wrapTransaction) {
                $pdo->commit();
            }
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM centroeduc_semana WHERE id_semana = ?');
        $stmt->execute([$id]);
        $stmt = $pdo->prepare('DELETE FROM centrotrabajo_semana WHERE id_semana = ?');
        $stmt->execute([$id]);
        $stmt = $pdo->prepare('DELETE FROM ciclos_semana WHERE id_semana = ?');
        $stmt->execute([$id]);

        $stmt = $pdo->prepare('DELETE FROM dias WHERE id_semana = ?');
        $stmt->execute([$id]);

        $stmt = $pdo->prepare('DELETE FROM informacion_semana WHERE id = ?');
        $stmt->execute([$id]);

        $references = [
            'informacion_del_centro_docente' => ['id' => $row['id_centro_docente']],
            'centro_trabajo' => ['id' => $row['id_centro_trabajo']],
            'ciclos' => ['id' => $row['id_ciclo']],
        ];

        foreach ($references as $table => $data) {
            $rowId = $data['id'];
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM centroeduc_semana WHERE id_centro_docente = ?");
            if ($table === 'centro_trabajo') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM centrotrabajo_semana WHERE id_centro_trabajo = ?");
            } elseif ($table === 'ciclos') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM ciclos_semana WHERE id_ciclo = ?");
            }
            $stmt->execute([$rowId]);
            $count = (int) $stmt->fetchColumn();
            if ($count === 0) {
                $pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$rowId]);
            }
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
        s.id,
        s.updated_at,
        s.diainicio,
        s.diafin,
        s.mes,
        s.ano,
        d.nombre_alumno
        FROM informacion_semana s
        INNER JOIN centroeduc_semana ces ON ces.id_semana = s.id
        INNER JOIN informacion_del_centro_docente d ON d.id = ces.id_centro_docente
        ORDER BY s.updated_at DESC, s.id DESC");

    return $stmt->fetchAll();
}

function fetch_report_values(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("SELECT
        s.id,
        s.hoja1,
        s.hoja2,
        s.diainicio,
        s.diafin,
        s.mes,
        s.ano,
        s.firma_alumno,
        s.firma_profe,
        s.firma_tutor,
        d.centro_docente,
        d.nombre_y_apellidos,
        d.nombre_alumno,
        t.nombre_centro_trabajo,
        t.tutor_trabajo,
        c.nombre AS ciclo,
        c.grado
        FROM informacion_semana s
        INNER JOIN centroeduc_semana ces ON ces.id_semana = s.id
        INNER JOIN informacion_del_centro_docente d ON d.id = ces.id_centro_docente
        INNER JOIN centrotrabajo_semana cts ON cts.id_semana = s.id
        INNER JOIN centro_trabajo t ON t.id = cts.id_centro_trabajo
        INNER JOIN ciclos_semana cs ON cs.id_semana = s.id
        INNER JOIN ciclos c ON c.id = cs.id_ciclo
        WHERE s.id = ?
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

    $stmt = $pdo->prepare("SELECT actividades, tiempo, observaciones, posicion
        FROM dias
        WHERE id_semana = ?
        ORDER BY posicion ASC");
    $stmt->execute([$id]);

    foreach ($stmt->fetchAll() as $dia) {
        $position = (int) $dia['posicion'];
        $values['actividad_' . $position] = (string) ($dia['actividades'] ?? '');
        $values['tiempo_' . $position] = (string) ($dia['tiempo'] ?? '');
        $values['observaciones_' . $position] = (string) ($dia['observaciones'] ?? '');
    }

    return $values;
}

function fetch_all_centros_docentes(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, nombre_y_apellidos, nombre_alumno, centro_docente FROM informacion_del_centro_docente ORDER BY nombre_alumno ASC");
    return $stmt->fetchAll();
}

function fetch_all_centros_trabajo(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, nombre_centro_trabajo, tutor_trabajo FROM centro_trabajo ORDER BY nombre_centro_trabajo ASC");
    return $stmt->fetchAll();
}

function fetch_all_ciclos(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, nombre, grado FROM ciclos ORDER BY nombre ASC");
    return $stmt->fetchAll();
}

function fetch_all_semanas(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id, hoja1, hoja2, diainicio, diafin, mes, ano FROM informacion_semana ORDER BY ano DESC, mes DESC, diainicio DESC");
    return $stmt->fetchAll();
}

function insert_centro_docente(PDO $pdo, string $nombre_y_apellidos, string $nombre_alumno, string $centro_docente): int
{
    return insert_report_row(
        $pdo,
        'INSERT INTO informacion_del_centro_docente (nombre_y_apellidos, nombre_alumno, centro_docente) VALUES (?, ?, ?)',
        [$nombre_y_apellidos ?: null, $nombre_alumno ?: null, $centro_docente ?: null]
    );
}

function insert_centro_trabajo(PDO $pdo, string $nombre_centro_trabajo, string $tutor_trabajo): int
{
    return insert_report_row(
        $pdo,
        'INSERT INTO centro_trabajo (nombre_centro_trabajo, tutor_trabajo) VALUES (?, ?)',
        [$nombre_centro_trabajo ?: null, $tutor_trabajo ?: null]
    );
}

function insert_ciclo(PDO $pdo, string $nombre, string $grado): int
{
    return insert_report_row(
        $pdo,
        'INSERT INTO ciclos (nombre, grado) VALUES (?, ?)',
        [$nombre ?: null, $grado ?: null]
    );
}
