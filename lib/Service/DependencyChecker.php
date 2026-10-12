<?php
namespace OCA\UrbanDuplicati\Service;

/**
 * Checks the server-side runtime dependencies of the Python scanner.
 * The Nextcloud App Store package cannot install these, so the app has to
 * detect them and tell the admin what is missing.
 */
class DependencyChecker {
    public const PYTHON = '/usr/bin/python3';

    /** id => [label, import module, required, link] */
    private const PY_MODULES = [
        'pillow'      => ['Pillow',      'PIL',         true,  'https://pypi.org/project/pillow/'],
        'numpy'       => ['NumPy',       'numpy',       true,  'https://pypi.org/project/numpy/'],
        'pymysql'     => ['PyMySQL',     'pymysql',     true,  'https://pypi.org/project/PyMySQL/'],
        'imagehash'   => ['ImageHash',   'imagehash',   true,  'https://pypi.org/project/ImageHash/'],
        'pillow_heif' => ['pillow-heif', 'pillow_heif', false, 'https://pypi.org/project/pillow-heif/'],
    ];

    public function check(): array {
        $items = [];
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        $canExec = function_exists('exec') && !in_array('exec', $disabled, true);

        $pythonOk = $canExec && is_executable(self::PYTHON);
        $items[] = [
            'id' => 'python3', 'name' => 'Python 3', 'required' => true, 'installed' => $pythonOk,
            'url' => 'https://www.python.org/downloads/',
            'detail' => !$canExec ? 'PHP exec() is disabled' : ($pythonOk ? null : self::PYTHON . ' not found'),
        ];

        foreach (self::PY_MODULES as $id => [$label, $module, $required, $url]) {
            $installed = false;
            if ($pythonOk) {
                $out = [];
                exec(sprintf('%s -c %s 2>&1', escapeshellarg(self::PYTHON), escapeshellarg('import ' . $module)), $out, $code);
                $installed = ($code === 0);
            }
            $items[] = ['id' => $id, 'name' => $label, 'required' => $required, 'installed' => $installed, 'url' => $url, 'detail' => null];
        }

        $ffmpeg = false;
        if ($canExec) {
            $out = [];
            exec('command -v ffmpeg 2>&1', $out, $code);
            $ffmpeg = ($code === 0);
        }
        $items[] = ['id' => 'ffmpeg', 'name' => 'FFmpeg (video scanning)', 'required' => false, 'installed' => $ffmpeg,
                    'url' => 'https://ffmpeg.org/download.html', 'detail' => null];

        $missingRequired = array_values(array_filter($items, fn($i) => $i['required'] && !$i['installed']));
        $missingOptional = array_values(array_filter($items, fn($i) => !$i['required'] && !$i['installed']));

        return [
            'ok'              => count($missingRequired) === 0,
            'items'           => $items,
            'missing'         => array_column($missingRequired, 'name'),
            'missingOptional' => array_column($missingOptional, 'name'),
            'installCommand'  => 'apt-get update && apt-get install -y python3-pip python3-pil python3-numpy python3-pymysql ffmpeg'
                               . ' && python3 -m pip install --break-system-packages imagehash pillow-heif',
            'docsUrl'         => 'https://github.com/UrbanTechIO/urbanduplicati#requirements',
        ];
    }
}
