<?php
// Namespaces do not work here because when putting the widget class in a namespace wordpress
// won't find it.

// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

use MonsTec\Produck\ProduckApi;
use MonsTec\Produck\ProduckCache;
use MonsTec\Produck\ProduckConnector;

/**
 * Widget for showing a definite number of Quack Links,
 */
class ProduckQuacksWidget extends WP_Widget {
    // Main constructor
    // Note: The __()-function is for internationalisation, see:
    // https://make.wordpress.org/polyglots/handbook/#Localization_Technology
    public function __construct() {
        parent::__construct(
            'my_custom_widget',
            __( 'Produck Q&A Widget', 'text_domain' ),
            array(
                'customize_selective_refresh' => true,
            )
        );
    }

    // The widget form (for the backend )
    public function form($instance) {
        // Set widget defaults
        $defaults = array(
            'widgetTitle' => 'Aktuelle Q&As'
        );

        // Parse current settings with defaults, meaning for example, if there is no value for
        // 'widgetTitle' in $instance then use the value in $defaults.
        extract(wp_parse_args((array) $instance, $defaults)); ?>

        <?php // Widget Title ?>
        <p>
          <label for="<?php echo esc_attr($this->get_field_id('widgetTitle')); ?>"><?php _e('Widget Title', 'text_domain'); ?></label>
          <input class="widefat" id="<?php echo esc_attr($this->get_field_id('widgetTitle')); ?>" name="<?php echo esc_attr($this->get_field_name('widgetTitle')); ?>" type="text" value="<?php echo esc_attr($widgetTitle); ?>"/>
        </p>
        <?php
    }

    // Update widget settings
    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['widgetTitle'] = isset($new_instance['widgetTitle']) ? wp_strip_all_tags($new_instance['widgetTitle']) : '';
        return $instance;
    }

    // Display the widget
    public function widget($args, $instance) {
        extract($args);

        $produckApi = new ProduckApi(ProduckPlugin::getCustomerId());
        $produckCache = new ProduckCache();
        $connector = new ProduckConnector($produckApi, $produckCache);

        // Check the widget options
        $title = isset($instance['widgetTitle']) ? apply_filters('widget_title', $instance['widgetTitle']) : '';

        // WordPress core before_widget hook (always include )
        echo $before_widget;

        // Display the widget
        echo '<div class="widget-text wp_widget_plugin_box">';

        // Display widget title if defined
        if ($title) {
            echo $before_title.$title.$after_title;
        }

        $quackDisplayTarget = ProduckPlugin::isOpenQuackInNewPage() ? "_blank" : "";
        $quackData = $connector->getQuacks(ProduckPlugin::getNumberOfQuacksShown());

        echo   '<div id="quacks-external-box" class="quacks-main">';
        echo     '<section id="quacks-container">';
        echo       '<div id="quacklist-wrapper-external-box" class="quacks-block_content flush-left">';
        echo         '<div id="quack-overview-list-external-box" class="quacks-external-box">';

        if ($quackData != null) {
            foreach($quackData as $quack) {
                if (!isset($quack['title']) || strlen($quack['title']) < 1
                        || !isset($quack['quackId']) || strlen($quack['quackId']) < 1) {
                    continue;
                }

                $quackId = $quack['quackId'];
                $title = $quack['title'];

                $prettyUrlTitlePart = ProduckPlugin::transformTitleToUrlPart($title);
                $questionPath = '/quack/'.$quackId.'/'.$prettyUrlTitlePart;
                $quackLink = rtrim(home_url(), '/').$questionPath;

                echo           '<div class="quacks-dialogue-summary narrow">';
                echo             '<div class="quacks-summary-text">';
                echo               '<div class="quacks-text-line"><a class="quacks-question-hyperlink" href="'.$quackLink.'" target="'.$quackDisplayTarget.'">'.$title.'</a></div>';
                echo             '</div>';
                echo           '</div>';
            }
        } else {
            echo           '<p>';
            echo             'Chatten und Kaufen auf <a href="'.ProduckPlugin::getCustomerProduckLink().'" target="_blank">ProDuck.de</a>!';
            echo           '</p>';
        }

        echo         '</div>';
        echo         '<div class="quacks-more-quacks-ref">';
        echo           '<a href="'.ProduckPlugin::getQuackOverviewUrl().'" target="'.$quackDisplayTarget.'">Mehr Q&As</a>';
        echo         '</div>';
        echo       '</div>';
        echo     '<section>';
        echo   '</div>';


        echo '</div>';

        // WordPress core after_widget hook (always include )
        echo $after_widget;
    }
}
?>
