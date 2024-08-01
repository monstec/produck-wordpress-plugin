<?php
namespace MonsTec\Produck;

use ProduckPlugin;

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

class TemplateLoader {
    protected $pluginDir;
    protected $virtualTemplates = ['quackdetail.php', 'quacksoverview.php'];

    function __construct(String $pluginPath) {
        $this->pluginDir = $pluginPath;
    }

    public function init(Page $page) {
        if (ProduckPlugin::useThemeTemplate()) {
            $this->templates = array('page.php', 'index.php');
        } else {
            $this->templates = wp_parse_args(
                array('page.php', 'index.php'), (array) $page->getTemplate()
            );
        }
    }

    public function load() {
        do_action('template_redirect');

        global $isProduckVirtualPage;
        $isProduckVirtualPage = false;

        if (ProduckPlugin::useThemeTemplate()) {
            $template = locate_template(array_filter($this->templates));
        } else {
            $template = $this->locateVirtualPageTemplate(array_filter($this->templates));
        }
    
        if (!empty($template) && file_exists($template)) {

            if (in_array(basename($template), $this->virtualTemplates)) {
                $isProduckVirtualPage = true;
            }

            error_log('Loading template: ' . $template); // Debug statement
            require_once $template;
        } else {
            error_log('Template not found'); // Debug statement
        }
    }
    

    private function locateVirtualPageTemplate($templateNames) {
            $located = '';
            foreach ((array) $templateNames as $templateName) {
                if (!$templateName) {
                    continue;
                }

                $completeTemplatePath = $this->pluginDir.ProduckPlugin::TEMPLATE_SUB_DIR.'/'.$templateName;
                if (file_exists($completeTemplatePath)) {
                    $located = $completeTemplatePath;
                    return $located;
                }
            }

            // fallback to themes
            return locate_template($templateNames);
    }
}
?>