<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

set_exception_handler(function(Throwable $e) {
    echo '<pre style="color:red;background:white;padding:20px;">';
    echo 'Error: ' . htmlspecialchars($e->getMessage()) . "\n\n";
    echo 'File: ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n\n";
    echo htmlspecialchars($e->getTraceAsString());
    echo '</pre>';
    exit;
});

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

$pdfName = 'Ficha_semanal_alumno_seneca_rellenable (1) (1).pdf';
$pdfPath = __DIR__ . DIRECTORY_SEPARATOR . $pdfName;
$fillableFields = [
    'hoja1', 'hoja2', 'dia1', 'dia2', 'ano', 'mes',
    'centro_docente', 'centro_trabajo', 'tutor', 'profesor',
    'alumno', 'ciclo', 'firma_alumno', 'firma_profe', 'firma_tutor', 'grado',
    'actividad_0', 'actividad_1', 'actividad_2', 'actividad_3', 'actividad_4',
    'tiempo_0', 'tiempo_1', 'tiempo_2', 'tiempo_3', 'tiempo_4',
    'observaciones_0', 'observaciones_1', 'observaciones_2', 'observaciones_3', 'observaciones_4',
];

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function field(array $source, string $name): string
{
    return trim((string)($source[$name] ?? ''));
}

function clean_form_values(array $source, array $fields): array
{
    $values = [];

    foreach ($fields as $name) {
        $values[$name] = field($source, $name);
    }

    return $values;
}

function form_title(array $values): string
{
    $parts = array_filter([
        field($values, 'alumno'),
        field($values, 'dia1') && field($values, 'dia2') ? field($values, 'dia1') . '-' . field($values, 'dia2') : '',
        field($values, 'mes'),
        field($values, 'ano'),
    ]);

    return $parts ? implode(' | ', $parts) : 'Ficha sin nombre';
}

function pdf_hex_string(string $value): string
{
    if (!function_exists('mb_convert_encoding')) {
        throw new RuntimeException('La extensión mbstring no está instalada.');
    }
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $utf16 = mb_convert_encoding($value, 'UTF-16BE', 'UTF-8');
    return '<FEFF' . strtoupper(bin2hex($utf16)) . '>';
}

function pdf_object(string $pdf, int $number): string
{
    $pattern = '/\n' . $number . '\s+0\s+obj\s*(.*?)\s*endobj/s';
    if (!preg_match($pattern, $pdf, $matches)) {
        throw new RuntimeException('No se pudo encontrar el objeto PDF ' . $number . '.');
    }

    return trim($matches[1]);
}

function set_pdf_entry(string $dictionary, string $key, string $value): string
{
    $dictionary = preg_replace('/\s*\/' . preg_quote($key, '/') . '\s+(?:<[^>]*>|\([^)]*\)|true|false|[^\s<>\/\[]+)/s', '', $dictionary);
    return preg_replace('/>>\s*$/', "\n/$key $value\n>>", $dictionary);
}

function make_filled_pdf(string $pdfPath, array $values): string
{
    if (!is_file($pdfPath)) {
        throw new RuntimeException('No se encontro el PDF original.');
    }

    $pdf = file_get_contents($pdfPath);
    if ($pdf === false || !preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/s', $pdf, $xrefMatch)) {
        throw new RuntimeException('No se pudo leer la estructura del PDF.');
    }

    $fieldObjects = [
        'hoja1' => 9,
        'hoja2' => 10,
        'dia1' => 11,
        'dia2' => 12,
        'ano' => 13,
        'mes' => 14,
        'centro_docente' => 15,
        'centro_trabajo' => 16,
        'tutor' => 17,
        'profesor' => 18,
        'alumno' => 22,
        'ciclo' => 23,
        'firma_alumno' => 24,
        'firma_profe' => 25,
        'firma_tutor' => 26,
        'grado' => 27,
        'actividad_0' => 35,
        'actividad_1' => 36,
        'actividad_2' => 37,
        'actividad_3' => 38,
        'actividad_4' => 39,
        'tiempo_0' => 40,
        'tiempo_1' => 41,
        'tiempo_2' => 42,
        'tiempo_3' => 43,
        'tiempo_4' => 44,
        'observaciones_0' => 45,
        'observaciones_1' => 46,
        'observaciones_2' => 47,
        'observaciones_3' => 48,
        'observaciones_4' => 49,
    ];

    $updates = [];
    $acroForm = pdf_object($pdf, 2);
    $acroForm = set_pdf_entry($acroForm, 'NeedAppearances', 'true');
    $updates[2] = $acroForm;

    foreach ($fieldObjects as $fieldName => $objectNumber) {
        $value = trim((string)($values[$fieldName] ?? ''));
        if ($value === '') {
            continue;
        }

        $dictionary = pdf_object($pdf, $objectNumber);
        $dictionary = set_pdf_entry($dictionary, 'V', pdf_hex_string($value));
        $updates[$objectNumber] = $dictionary;
    }

    ksort($updates);
    $append = "\n";
    $offsets = [];

    foreach ($updates as $objectNumber => $body) {
        $offsets[$objectNumber] = strlen($pdf) + strlen($append);
        $append .= $objectNumber . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf) + strlen($append);
    $append .= "xref\n";

    foreach ($offsets as $objectNumber => $offset) {
        $append .= $objectNumber . " 1\n";
        $append .= sprintf("%010d 00000 n \n", $offset);
    }

    $append .= "trailer\n<<\n/Size 63\n/Root 1 0 R\n/Prev " . $xrefMatch[1] . "\n>>\n";
    $append .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

    return $pdf . $append;
}

$message = '';
$messageType = 'success';
$pdo = null;
$dbError = '';

try {
    $pdo = report_db();
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
}

$savedForms = $pdo ? fetch_saved_reports($pdo) : [];
$centros_docentes = $pdo ? fetch_all_centros_docentes($pdo) : [];
$centros_trabajo = $pdo ? fetch_all_centros_trabajo($pdo) : [];
$ciclos = $pdo ? fetch_all_ciclos($pdo) : [];
$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$formValues = [];
$selected_centro_docente_id = null;
$selected_centro_trabajo_id = null;
$selected_ciclo_id = null;

if ($pdo && $selectedId) {
    try {
        $formValues = fetch_report_values($pdo, $selectedId);
        if (!$formValues) {
            $selectedId = null;
        } else {
            $stmt = $pdo->prepare("SELECT ces.id_centro_docente, cts.id_centro_trabajo, cs.id_ciclo
                FROM centroeduc_semana ces
                INNER JOIN centrotrabajo_semana cts ON cts.id_semana = ces.id_semana
                INNER JOIN ciclos_semana cs ON cs.id_semana = ces.id_semana
                WHERE ces.id_semana = ?");
            $stmt->execute([$selectedId]);
            $relatedIds = $stmt->fetch();
            $selected_centro_docente_id = $relatedIds['id_centro_docente'] ?? null;
            $selected_centro_trabajo_id = $relatedIds['id_centro_trabajo'] ?? null;
            $selected_ciclo_id = $relatedIds['id_ciclo'] ?? null;
        }
    } catch (Throwable $e) {
        $message = 'Error al cargar la ficha: ' . $e->getMessage();
        $messageType = 'error';
        $selectedId = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = field($_POST, 'action') ?: 'download';
    $postedId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
    $selectedId = $postedId;
    $formValues = clean_form_values($_POST, $fillableFields);

    try {
        if (!$pdo) {
            throw new RuntimeException('No hay conexion con la base de datos: ' . $dbError);
        }

        if ($action === 'delete') {
            if ($selectedId) {
                delete_report($pdo, $selectedId);
            }
            $message = 'Ficha eliminada.';
            $selectedId = null;
            $formValues = [];
        } elseif ($action === 'save') {
            $existing_docente_id = filter_input(INPUT_POST, 'existing_centro_docente_id', FILTER_VALIDATE_INT) ?: null;
            $existing_trabajo_id = filter_input(INPUT_POST, 'existing_centro_trabajo_id', FILTER_VALIDATE_INT) ?: null;
            $existing_ciclo_id = filter_input(INPUT_POST, 'existing_ciclo_id', FILTER_VALIDATE_INT) ?: null;
            
            $selectedId = save_report($pdo, $formValues, $selectedId, $existing_docente_id, $existing_trabajo_id, $existing_ciclo_id);
            $message = 'Ficha guardada.';
        } elseif ($action === 'export_all') {
            $exportDir = __DIR__ . DIRECTORY_SEPARATOR . 'exports';
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0777, true);
            }

            $allReports = fetch_saved_reports($pdo);
            $exported = 0;

            foreach ($allReports as $report) {
                $values = fetch_report_values($pdo, (int)$report['id']);
                if (!$values) {
                    continue;
                }

                $filledPdf = make_filled_pdf($pdfPath, $values);
                $fileName = 'ficha_' . $report['id'] . '_' . date('Ymd_His', strtotime($report['updated_at'])) . '.pdf';
                $filePath = $exportDir . DIRECTORY_SEPARATOR . $fileName;

                file_put_contents($filePath, $filledPdf);
                $exported++;
            }

            $message = "Se exportaron {$exported} fichas a la carpeta 'exports'.";
        } else {
            $filledPdf = make_filled_pdf($pdfPath, $formValues);
            $fileName = 'ficha_semanal_rellena_' . date('Ymd_His') . '.pdf';

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . strlen($filledPdf));
            echo $filledPdf;
            exit;
        }
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
    }
}

$savedForms = $pdo ? fetch_saved_reports($pdo) : [];
$formId = (string)($selectedId ?: '');
$rows = [
    ['0', '1'],
    ['1', '2'],
    ['2', '3'],
    ['3', '4'],
    ['4', '5'],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rellenador de ficha semanal</title>
    <script>
        const centrosDocentes = <?= json_encode($centros_docentes) ?>;
        const centrosTrabajo = <?= json_encode($centros_trabajo) ?>;
        const ciclos = <?= json_encode($ciclos) ?>;

        function fillCentroDocente(select) {
            const selectedId = select.value;
            if (!selectedId) {
                document.getElementsByName('centro_docente')[0].value = '';
                document.getElementsByName('profesor')[0].value = '';
                document.getElementsByName('alumno')[0].value = '';
                document.getElementById('existing_centro_docente_id').value = '';
                return;
            }
            const data = centrosDocentes.find(item => item.id == selectedId);
            if (data) {
                document.getElementsByName('centro_docente')[0].value = data.centro_docente || '';
                document.getElementsByName('profesor')[0].value = data.nombre_y_apellidos || '';
                document.getElementsByName('alumno')[0].value = data.nombre_alumno || '';
                document.getElementById('existing_centro_docente_id').value = data.id;
            }
        }

        function resetCentroDocente() {
            document.getElementById('existing_centro_docente_select').value = '';
            document.getElementById('existing_centro_docente_id').value = '';
        }

        function fillCentroTrabajo(select) {
            const selectedId = select.value;
            if (!selectedId) {
                document.getElementsByName('centro_trabajo')[0].value = '';
                document.getElementsByName('tutor')[0].value = '';
                document.getElementById('existing_centro_trabajo_id').value = '';
                return;
            }
            const data = centrosTrabajo.find(item => item.id == selectedId);
            if (data) {
                document.getElementsByName('centro_trabajo')[0].value = data.nombre_centro_trabajo || '';
                document.getElementsByName('tutor')[0].value = data.tutor_trabajo || '';
                document.getElementById('existing_centro_trabajo_id').value = data.id;
            }
        }

        function resetCentroTrabajo() {
            document.getElementById('existing_centro_trabajo_select').value = '';
            document.getElementById('existing_centro_trabajo_id').value = '';
        }

        function fillCiclo(select) {
            const selectedId = select.value;
            if (!selectedId) {
                document.getElementsByName('ciclo')[0].value = '';
                document.getElementsByName('grado')[0].value = '';
                document.getElementById('existing_ciclo_id').value = '';
                return;
            }
            const data = ciclos.find(item => item.id == selectedId);
            if (data) {
                document.getElementsByName('ciclo')[0].value = data.nombre || '';
                document.getElementsByName('grado')[0].value = data.grado || '';
                document.getElementById('existing_ciclo_id').value = data.id;
            }
        }

        function resetCiclo() {
            document.getElementById('existing_ciclo_select').value = '';
            document.getElementById('existing_ciclo_id').value = '';
        }

        window.addEventListener('DOMContentLoaded', () => {
            const centroDocenteSelect = document.getElementById('existing_centro_docente_select');
            if (centroDocenteSelect.value) fillCentroDocente(centroDocenteSelect);
            
            const centroTrabajoSelect = document.getElementById('existing_centro_trabajo_select');
            if (centroTrabajoSelect.value) fillCentroTrabajo(centroTrabajoSelect);
            
            const cicloSelect = document.getElementById('existing_ciclo_select');
            if (cicloSelect.value) fillCiclo(cicloSelect);
        });
    </script>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f6;
            color: #17202a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .shell {
            display: grid;
            grid-template-columns: 390px minmax(0, 1fr);
            min-height: 100vh;
        }

        .panel {
            border-right: 1px solid #cbd5e1;
            background: #ffffff;
            padding: 18px;
            overflow: auto;
            max-height: 100vh;
        }

        .pdf {
            min-height: 100vh;
            padding: 18px;
        }

        .pdf object {
            width: 100%;
            height: calc(100vh - 36px);
            border: 1px solid #94a3b8;
            background: #ffffff;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 22px;
        }

        p {
            margin: 0 0 16px;
            color: #526173;
        }

        fieldset {
            border: 1px solid #d8dee7;
            border-radius: 8px;
            margin: 0 0 14px;
            padding: 14px;
        }

        legend {
            padding: 0 6px;
            color: #0f766e;
            font-weight: 700;
        }

        label {
            display: grid;
            gap: 5px;
            margin-bottom: 10px;
            color: #526173;
            font-size: 13px;
            font-weight: 700;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 9px 10px;
            color: #17202a;
            font: inherit;
            font-weight: 400;
        }

        textarea {
            min-height: 72px;
            resize: vertical;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .row-title {
            margin: 6px 0 9px;
            font-size: 14px;
            font-weight: 700;
        }

        .actions {
            position: sticky;
            bottom: -18px;
            display: grid;
            gap: 8px;
            margin: 14px -18px -18px;
            border-top: 1px solid #d8dee7;
            background: #ffffff;
            padding: 14px 18px 18px;
        }

        button,
        .button {
            border: 0;
            border-radius: 8px;
            min-height: 42px;
            padding: 0 14px;
            background: #0f766e;
            color: #ffffff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        button.secondary,
        .button.secondary {
            background: #e8eef5;
            color: #17202a;
        }

        button.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .error,
        .success {
            margin-bottom: 14px;
            border-radius: 8px;
            padding: 10px;
            font-weight: 700;
        }

        .error {
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #991b1b;
        }

        .success {
            border: 1px solid #99f6e4;
            background: #ecfdf5;
            color: #0f766e;
        }

        .saved-list {
            display: grid;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .saved-list a {
            display: block;
            border: 1px solid #d8dee7;
            border-radius: 8px;
            padding: 9px 10px;
            color: #17202a;
            text-decoration: none;
        }

        .saved-list a:hover {
            border-color: #0f766e;
            background: #f0fdfa;
        }

        .saved-list strong,
        .saved-list span {
            display: block;
        }

        .saved-list span {
            margin-top: 3px;
            color: #64748b;
            font-size: 12px;
        }

        @media (max-width: 980px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .panel {
                max-height: none;
                border-right: 0;
                border-bottom: 1px solid #cbd5e1;
            }

            .pdf object {
                height: 78vh;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <form class="panel" method="post" autocomplete="on">
            <input type="hidden" name="id" value="<?= h($formId) ?>">
            <input type="hidden" name="existing_centro_docente_id" id="existing_centro_docente_id" value="<?= h((string)($selected_centro_docente_id ?? '')) ?>">
            <input type="hidden" name="existing_centro_trabajo_id" id="existing_centro_trabajo_id" value="<?= h((string)($selected_centro_trabajo_id ?? '')) ?>">
            <input type="hidden" name="existing_ciclo_id" id="existing_ciclo_id" value="<?= h((string)($selected_ciclo_id ?? '')) ?>">
            <h1>Ficha semanal</h1>
            <p>El documento de la derecha es el PDF original. Puedes guardar la ficha y descargar una copia rellena.</p>

            <?php if ($message): ?>
                <div class="<?= h($messageType) ?>"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($dbError): ?>
                <div class="error">No se pudo conectar con la base de datos: <?= h($dbError) ?></div>
            <?php endif; ?>

            <fieldset>
                <legend>Fichas guardadas</legend>
                <?php if (!$savedForms): ?>
                    <p>No hay fichas guardadas todavia.</p>
                <?php else: ?>
                    <ul class="saved-list">
                        <?php foreach ($savedForms as $savedForm): ?>
                            <li>
                                <a href="?id=<?= h((string)($savedForm['id'] ?? '')) ?>">
                                    <strong><?= h(form_title([
                                        'alumno' => (string)($savedForm['nombre_alumno'] ?? ''),
                                        'dia1' => (string)($savedForm['diainicio'] ?? ''),
                                        'dia2' => (string)($savedForm['diafin'] ?? ''),
                                        'mes' => (string)($savedForm['mes'] ?? ''),
                                        'ano' => (string)($savedForm['ano'] ?? ''),
                                    ])) ?></strong>
                                    <span><?= h(date('d/m/Y H:i', strtotime((string)($savedForm['updated_at'] ?? 'now')))) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </fieldset>

            <fieldset>
                <legend>Cabecera</legend>
                <div class="grid">
                    <label>Hoja
                        <input name="hoja1" maxlength="2" value="<?= h(field($formValues, 'hoja1')) ?>">
                    </label>
                    <label>De
                        <input name="hoja2" maxlength="2" value="<?= h(field($formValues, 'hoja2')) ?>">
                    </label>
                    <label>Dia inicio
                        <input name="dia1" maxlength="2" value="<?= h(field($formValues, 'dia1')) ?>">
                    </label>
                    <label>Dia fin
                        <input name="dia2" maxlength="2" value="<?= h(field($formValues, 'dia2')) ?>">
                    </label>
                    <label>Mes
                        <input name="mes" value="<?= h(field($formValues, 'mes')) ?>">
                    </label>
                    <label>Ano
                        <input name="ano" maxlength="4" value="<?= h(field($formValues, 'ano')) ?>">
                    </label>
                </div>
            </fieldset>

            <fieldset>
                <legend>Datos</legend>
                
                <label>Seleccionar entrada existente de centro docente
                    <select name="existing_centro_docente_select" id="existing_centro_docente_select" onchange="fillCentroDocente(this)">
                        <option value="">-- Nueva entrada --</option>
                        <?php foreach ($centros_docentes as $cd): ?>
                            <option value="<?= h($cd['id']) ?>" <?= $selected_centro_docente_id == $cd['id'] ? 'selected' : '' ?>>
                                <?= h($cd['nombre_alumno'] . ' - ' . $cd['centro_docente']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Centro docente
                    <input name="centro_docente" value="<?= h(field($formValues, 'centro_docente')) ?>" oninput="resetCentroDocente()">
                </label>
                <label>Profesor/a responsable
                    <input name="profesor" value="<?= h(field($formValues, 'profesor')) ?>" oninput="resetCentroDocente()">
                </label>
                <label>Alumno/a
                    <input name="alumno" value="<?= h(field($formValues, 'alumno')) ?>" oninput="resetCentroDocente()">
                </label>
                
                <label>Seleccionar entrada existente de centro de trabajo
                    <select name="existing_centro_trabajo_select" id="existing_centro_trabajo_select" onchange="fillCentroTrabajo(this)">
                        <option value="">-- Nueva entrada --</option>
                        <?php foreach ($centros_trabajo as $ct): ?>
                            <option value="<?= h($ct['id']) ?>" <?= $selected_centro_trabajo_id == $ct['id'] ? 'selected' : '' ?>>
                                <?= h($ct['nombre_centro_trabajo'] . ' - ' . $ct['tutor_trabajo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Centro de trabajo
                    <input name="centro_trabajo" value="<?= h(field($formValues, 'centro_trabajo')) ?>" oninput="resetCentroTrabajo()">
                </label>
                <label>Tutor/a laboral
                    <input name="tutor" value="<?= h(field($formValues, 'tutor')) ?>" oninput="resetCentroTrabajo()">
                </label>
                
                <label>Seleccionar entrada existente de ciclo
                    <select name="existing_ciclo_select" id="existing_ciclo_select" onchange="fillCiclo(this)">
                        <option value="">-- Nueva entrada --</option>
                        <?php foreach ($ciclos as $c): ?>
                            <option value="<?= h($c['id']) ?>" <?= $selected_ciclo_id == $c['id'] ? 'selected' : '' ?>>
                                <?= h($c['nombre'] . ' - ' . $c['grado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="grid">
                    <label>Ciclo formativo
                        <input name="ciclo" value="<?= h(field($formValues, 'ciclo')) ?>" oninput="resetCiclo()">
                    </label>
                    <label>Grado
                        <input name="grado" value="<?= h(field($formValues, 'grado')) ?>" oninput="resetCiclo()">
                    </label>
                </div>
            </fieldset>

            <fieldset>
                <legend>Actividades</legend>
                <?php foreach ($rows as [$index, $number]): ?>
                    <div class="row-title">Fila <?= h($number) ?></div>
                    <label>Actividad
                        <textarea name="actividad_<?= h($index) ?>"><?= h(field($formValues, 'actividad_' . $index)) ?></textarea>
                    </label>
                    <label>Tiempo
                        <textarea name="tiempo_<?= h($index) ?>"><?= h(field($formValues, 'tiempo_' . $index)) ?></textarea>
                    </label>
                    <label>Observaciones
                        <textarea name="observaciones_<?= h($index) ?>"><?= h(field($formValues, 'observaciones_' . $index)) ?></textarea>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <fieldset>
                <legend>Firmas</legend>
                <label>Firma alumno/a
                    <input name="firma_alumno" value="<?= h(field($formValues, 'firma_alumno')) ?>">
                </label>
                <label>Firma profesor/a
                    <input name="firma_profe" value="<?= h(field($formValues, 'firma_profe')) ?>">
                </label>
                <label>Firma tutor/a
                    <input name="firma_tutor" value="<?= h(field($formValues, 'firma_tutor')) ?>">
                </label>
            </fieldset>

            <div class="actions">
                <button type="submit" name="action" value="save">Guardar ficha</button>
                <button type="submit" name="action" value="download">Descargar PDF relleno</button>
                <button type="submit" name="action" value="export_all" formnovalidate onclick="return confirm('Seguro que quieres exportar todas las fichas a la carpeta exports?')">Exportar todas</button>
                <?php if ($selectedId): ?>
                    <button class="danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Seguro que quieres eliminar esta ficha?')">Eliminar ficha</button>
                <?php endif; ?>
                <a class="button secondary" href="<?= h($pdfName) ?>" target="_blank" rel="noopener">Abrir PDF original</a>
            </div>
        </form>

        <main class="pdf">
            <object data="<?= h($pdfName) ?>" type="application/pdf">
                <a href="<?= h($pdfName) ?>">Abrir PDF original</a>
            </object>
        </main>
    </div>
</body>
</html>
