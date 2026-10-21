<?php
/**
 * src/classes/View.php
 * Renders plain-PHP templates from src/templates/, optionally wrapped in a layout.
 */

class View {
    private const TEMPLATE_DIR = __DIR__ . '/../templates';

    /**
     * Renders $template with $vars. When $layout is given, the rendered template is passed
     * to the layout as $content together with the same $vars.
     */
    public static function render(string $template, array $vars = [], ?string $layout = null): void {
        $content = self::capture($template, $vars);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::capture('layout/' . $layout, $vars + ['content' => $content]);
    }

    private static function capture(string $template, array $vars): string {
        if (!preg_match('#^[a-z0-9_/-]+$#', $template)) {
            throw new InvalidArgumentException("Invalid template name: {$template}");
        }
        $file = self::TEMPLATE_DIR . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Template not found: {$template}");
        }

        ob_start();
        (static function (string $__file, array $__vars) {
            extract($__vars, EXTR_SKIP);
            include $__file;
        })($file, $vars);
        return (string)ob_get_clean();
    }
}
