<?php
// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

/**
 * Produck Plugin Administration, where values of properties like customerId can be set by the plugin user.
 * The creation of this class followed this tutorial:
 * http://ottopress.com/2009/wordpress-settings-api-tutorial/
 */
class ProduckPluginAdministration {
    public function __construct() {
        add_action('admin_menu', array($this, 'addPluginAdminPage'));
        add_action('admin_init', array($this, 'pluginAdminInit'));
        add_action('admin_head', function() {
            echo '<link rel="stylesheet" href="'
                .ProduckPlugin::getPluginUrl().'/css/administration.min.css'
                .'" type="text/css" media="all" />';
        });

        add_action('wp_ajax_produck_first_config', array($this, 'handleFirstConfigAnswer'));
    }

    /**
     * Contains the menu-building code.
     */
    public function addPluginAdminPage() {
        add_options_page('Produck Einstellungen', // displayed in title tags
                         'Produck', // text to be used for the menu
                         'manage_options', //capability required for the menu to be displayed
                         'produck-settings', // slug name used to refer to this menu (should be unique)
                         array($this, 'createPluginOptionsPage')); // callback function that will output the page content

        if (ProduckPlugin::isPoweredByLinkAllowed() < 0) {
            add_thickbox();
            add_action('produck_administration_requested',
                       array($this, 'showFirstConfigurationDialogue'));
        }
    }

    /**
     * Create the HTML output for the page (screen) displayed when the menu item is clicked.
    */
    public function createPluginOptionsPage() {
        if (!current_user_can( 'manage_options')) {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        do_action('produck_administration_requested');

        ?>
        <div>
        <h2>Produck Konfiguration</h2>
        Hier finden Sie Einstellungen für das Produck-Plugin.
        <form action="options.php" method="post">
        <?php settings_fields('produck_options'); ?>
        <?php do_settings_sections('produck_settings_page'); ?>
        <input class="button button-primary" name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
        </form>
        </div>
        <?php
    }

    /**
     * Adds sesctions and field definitions.
     * For each section a callback is used to define a descriptive text. Sections are not necessary but just means to
     * logically group options/fields.
     * For each field a callback is used to define the actual HTML code for the field.
     */
    public function pluginAdminInit(){
        register_setting('produck_options', 'produck_config', array($this, 'validateProduckOptions'));

        add_settings_section('produck_settings_general', 'Allgemein', array($this, 'generalSectionText'), 'produck_settings_page');
        add_settings_field('produckCustomerIdField', 'Produck Benutzer ID:', array($this, 'createCustomerIdInputField'), 'produck_settings_page', 'produck_settings_general');

        add_settings_section('produck_settings_chat', 'Chat', array($this, 'chatSectionText'), 'produck_settings_page');
        add_settings_field('chatEnabledField', 'Chat aktiviert?', array($this, 'createChatEnabledField'), 'produck_settings_page', 'produck_settings_chat');

        add_settings_section('produck_settings_quacks', 'Quacks', array($this, 'quacksSectionText'), 'produck_settings_page');
        add_settings_field('openQuackInNewPageField', 'Quacks in neuem Fenster öffnen?', array($this, 'createOpenQuackInNewPageField'), 'produck_settings_page', 'produck_settings_quacks');
        add_settings_field('maxQuackUrlTitleLengthField', 'Max. Anzahl an Zeichen für den Titel in der URL:', array($this, 'createMaxQuackUrlTitleLengthField'), 'produck_settings_page', 'produck_settings_quacks');
        add_settings_field('useThemeTemplateField', 'Theme-eigenes Seitentemplate verwenden?', array($this, 'createUseThemeTemplateField'), 'produck_settings_page', 'produck_settings_quacks');
        add_settings_field('poweredByLinkAllowedField', '"Powered by Produck"-Links anzeigen?', array($this, 'createPoweredByLinkAllowedField'), 'produck_settings_page', 'produck_settings_quacks');

        add_settings_section('produck_settings_widget', 'Widget', array($this, 'widgetSectionText'), 'produck_settings_page');
        add_settings_field('numberOfQuacksShownField', 'Max. Anzahl angezeigter Quacks', array($this, 'createNumberOfQuacksShownField'), 'produck_settings_page', 'produck_settings_widget');
    }

    /**
     * Defines the description for the section 'general settings'.
     */
    public function generalSectionText() {
        echo '<p>Nehmen sie hier grundsätzliche Einstellungen vor, welche den Betrieb des Plugins ermöglichen. Die Produck '
             .'Benutzer ID finden Sie in Ihrem Profil auf <a href="https://www.produck.de/xpert.html" target="_blank">www.produck.de</a>.';
    }

    /**
     * Defines the description for the section 'chat settings'.
     */
    public function chatSectionText() {
        echo '<p>Wenn Sie den Produckchat direkt auf Ihrer Wordpressseite einsetzen wollen, können Sie ihn hier aktivieren.</p>';
    }

    /**
     * Defines the description for the section 'quacks settings'.
     */
    public function quacksSectionText() {
        echo '<p>Definieren Sie hier ob Links zu einzelnen Quacks und zur Quacksübersicht in einem neuen Fenster bzw. '
             .'Tab geöffnet werden sollen (oder im aktuellen Fenster).<br/>'
             .'Außerdem können Sie die maximale Länge der URL von einzelnen Quacks beeinflussen, indem sie die maximale '
             .'Länge des Teils, der den Titel darstellt, einstellen.<br/>'
             .'Schließlich können Sie wählen, ob Sie das vom Produck-Plugin mitgelieferte Seitentemplate für Quacks-'
             .'spezifische Seiten verwenden wollen, oder lieber das Seitentemplate des von Ihnen verwendeten Themes.</p>';
    }

    /**
     * Defines the description for the section 'widget settings'.
     */
    public function widgetSectionText() {
        echo '<p>Hier können Sie die Anzahl der maximal angezeigten Quacks im Quacks-Widgets definieren.'
             .'Beachten Sie bitte, dass Sie das Widget noch unter "Design->Widgets" zu den anzuzeigenden Widgets '
             .'hinzufügen müssen, wenn Sie es verwenden wollen.</p>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'customerId'.
     */
    public function createCustomerIdInputField() {
        $options = get_option('produck_config');
        echo '<input type="text" id="produckCustomerIdField" name="produck_config[customerId]" size="10" value="'.$options['customerId'].'"/>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'chatEnabled'.
     */
    public function createChatEnabledField() {
        $options = get_option('produck_config');
        echo '<select id="chatEnabledField" name="produck_config[chatEnabled]">';
        echo ' <option value="1"'.(($options['chatEnabled']) ? 'selected="selected"' : '').'>Ja</option>';
        echo ' <option value="0"'.((!$options['chatEnabled']) ? 'selected="selected"' : '').'>Nein</option>';
        echo '</select>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'openQuackInNewPage'.
     */
    public function createOpenQuackInNewPageField() {
        $options = get_option('produck_config');
        echo '<select id="openQuackInNewPageField" name="produck_config[openQuackInNewPage]">';
        echo ' <option value="1"'.(($options['openQuackInNewPage']) ? 'selected="selected"' : '').'>Ja</option>';
        echo ' <option value="0"'.((!$options['openQuackInNewPage']) ? 'selected="selected"' : '').'>Nein</option>';
        echo '</select>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'maxQuackUrlTitleLength'.
     */
    public function createMaxQuackUrlTitleLengthField() {
        $options = get_option('produck_config');
        echo '<input id="maxQuackUrlTitleLengthField" name="produck_config[maxQuackUrlTitleLength]" size="10" type="text" value="'.$options['maxQuackUrlTitleLength'].'"/>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'numberOfQuacksShown'.
     */
    public function createNumberOfQuacksShownField() {
        $options = get_option('produck_config');
        echo '<input id="numberOfQuacksShownField" name="produck_config[numberOfQuacksShown]" size="10" type="text" value="'.$options['numberOfQuacksShown'].'"/>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user decide whether to use the plugins built in
     * template for quack pages or the default theme page-template.
     */
    public function createUseThemeTemplateField() {
        $options = get_option('produck_config');
        echo '<select id="useThemeTemplateField" name="produck_config[useThemeTemplate]">';
        echo ' <option value="1"'.(($options['useThemeTemplate']) ? 'selected="selected"' : '').'>Ja</option>';
        echo ' <option value="0"'.((!$options['useThemeTemplate']) ? 'selected="selected"' : '').'>Nein</option>';
        echo '</select>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user decide whether to show powered-by-links or not.
     */
    public function createPoweredByLinkAllowedField() {
        $options = get_option('produck_config');
        echo '<select id="poweredByLinkAllowedField" name="produck_config[poweredByLinkAllowed]">';
        if ($options['poweredByLinkAllowed'] > 0) {
            echo ' <option value="1" selected="selected">Ja</option>';
            echo ' <option value="0">Nein</option>';
        } else {
            echo ' <option value="1">Ja</option>';
            echo ' <option value="0" selected="selected">Nein</option>';
        }
        echo '</select>';
    }

    /**
     * Validate the user's input on the settings page. The validated input will be returned and then stored
     * in the database by the settings-API.
     */
    public function validateProduckOptions($input) {
        // First load current configuration then only update those values that actually a treated in this
        // function. This way properties that are stored in the options array but cannot be updated via the
        // settings page are left as they are and not set to nothing or something undefined. This should just
        // be a saftey net. If there actually are such properties it should be strongly considered to place them
        // in another option. So for example there could be one option-(array) that stores changeable properties
        // and on other option(-array) that stores internal data that cannot be changed by the user.
        // And of course the original user input of this function (i.e. the argument $input) is not directly stored
        // as new value of the option, so that unwanted additional data in $input a user might have
        // sent along with the plugin settings is discarded.
        $trustedInput = get_option('produck_config');

        // trim whitespace and leading zeros off the customer-ID
        $inCustomerId = isset($input['customerId']) ? ltrim(trim($input['customerId']), '0') : '';
        if(preg_match('/^[1-9][0-9]{0,31}$/i', $inCustomerId)) {
            $trustedInput['customerId'] = $inCustomerId;
        } else {
            add_settings_error('produck_config', 'produckCustomerIdField',
                'Die Benutzer ID muss eine ganze Zahl sein.');
        }

        $inChatEnabled = isset($input['chatEnabled']) ? trim($input['chatEnabled']) : '';
        if(preg_match('/^[0|1]$/i', $inChatEnabled)) {
            $trustedInput['chatEnabled'] = $inChatEnabled;
        }

        $inopenQuackInNewPage = isset($input['openQuackInNewPage']) ? trim($input['openQuackInNewPage']) : '';
        if(preg_match('/^[0|1]$/i', $inopenQuackInNewPage)) {
            $trustedInput['openQuackInNewPage'] = $inopenQuackInNewPage;
        }

        $maxQuackUrlTitleLength = isset($input['maxQuackUrlTitleLength']) ? trim($input['maxQuackUrlTitleLength']) : '';
        if(preg_match('/^[1-9][0-9]{0,31}$/i', $maxQuackUrlTitleLength)) {
            $trustedInput['maxQuackUrlTitleLength'] = $maxQuackUrlTitleLength;
        } else {
            add_settings_error('produck_config', 'maxQuackUrlTitleLengthField',
                'Die Längenangabe für den Quacktitel muss eine ganze Zahl sein.');
        }

        $inNumberOfQuacksShown = isset($input['numberOfQuacksShown']) ? trim($input['numberOfQuacksShown']) : '';
        if(preg_match('/^[1-9][0-9]{0,31}$/i', $inNumberOfQuacksShown)) {
            $trustedInput['numberOfQuacksShown'] = $inNumberOfQuacksShown;
        } else {
            add_settings_error('produck_config', 'numberOfQuacksShownField',
            'Die Anzahl der angezeigten Quacks muss eine ganze Zahl sein.');
        }

        $useThemeTemplate = isset($input['useThemeTemplate']) ? trim($input['useThemeTemplate']) : '';
        if(preg_match('/^[0|1]$/i', $useThemeTemplate)) {
            $trustedInput['useThemeTemplate'] = $useThemeTemplate;
        }

        $poweredByLinkAllowed = isset($input['poweredByLinkAllowed']) ? trim($input['poweredByLinkAllowed']) : '';
        if(preg_match('/^[0|1]$/i', $poweredByLinkAllowed)) {
            $trustedInput['poweredByLinkAllowed'] = $poweredByLinkAllowed;
        }

        return $trustedInput;
    }

    public function showFirstConfigurationDialogue() {
        // inject a script that opens a thickox to ask for the user's permission to show powered-by-links
        ?>
        <script>
            jQuery(window).load(function($) {
                tb_show("Produck Konfiguration","#TB_inline?width=400&height=180&inlineId=produckFirstConfigDialogue", null);
            });

            function produck_sendFirstConfigAnswer() {
                var customerIdField = document.getElementById('produckFirstConfigCustomerIdField');
                var cid = customerIdField.value;
                var poweredByField = document.getElementById('produckFirstConfigPoweredByLinkAnswerField');
                var powby = poweredByField.value;

                var feedback;
                if (isNaN(cid) || cid < 1) {
                    feedback = "Kunden-ID ungültig";
                }

                if (isNaN(powby) || powby != 0 && powby != 1) {
                    if (feedback) {
                        feedback += "und Antwort ungültig";
                    } else {
                        feedback = "Antwort ungültig";
                    }
                }

                var feedbackCell = document.getElementById('produckFirstConfigFeedback');
                if (feedback) {
                    feedbackCell.innerHTML = feedback;
                    feedbackCell.style.display = 'flex';
                    return;
                } else {
                    feedbackCell.style.display = 'none';
                    feedbackCell.innerHTML = '';
                }

                var data = {
                    'action': 'produck_first_config',
                    'cid': cid,
                    'answer': powby,
                    'produckConfigFirstSaveNonce': document.getElementById('produckConfigFirstSaveNonce').value,
                    '_wp_http_referer': document.getElementsByName('_wp_http_referer')[0].value
                };

                document.getElementById('produckCustomerIdField').value = cid;

                if (powby > 0) {
                    document.getElementById("poweredByLinkAllowedField").selectedIndex = 0;
                } else {
                    document.getElementById("poweredByLinkAllowedField").selectedIndex = 1;
                }

                // since wordpress 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                jQuery.post(ajaxurl, data);
                tb_remove();
            }
        </script>
        <div id="produckFirstConfigDialogue">
          <div class="produck-admin-dialogue-pane">
            <div class="explanation-cell">
              Um Produck in Wordpress nutzen zu können, wird Ihre Produck Kunden-ID benötigt. Diese finden Sie in Ihrem Profil auf produck.de.
            </div>
            <div class="label-cell-cid">
              Produck Kunden-ID:
            </div>
            <div class="value-cell-cid">
              <input type="text" id="produckFirstConfigCustomerIdField"/>
            </div>
            <div class="label-cell-powby">
              Darf Produck durch &quot;Provided By&quot;-Links unter Quacks und der Quacks-Übersicht auf den Service aufmerksam machen?
            </div>
            <div class="value-cell-powby">
              <select id="produckFirstConfigPoweredByLinkAnswerField">
                <option value="1" selected="selected">Ja</option>
                <option value="0">Nein</option>
              </select>
            </div>
            <div id="produckFirstConfigFeedback" class="feedback-cell">
            </div>
            <div class="button-cell">
              <button class="button button-primary" onclick="javascript:produck_sendFirstConfigAnswer();">Speichern</button>
              <button class="button" onclick="javascript:tb_remove();return false;">Abbruch</button>
              <?php
                wp_nonce_field('produck_config_first_save', 'produckConfigFirstSaveNonce');
              ?>
            </div>
          </div>
        </div>
        <?php
    }

    public function handleFirstConfigAnswer() {
        if(!current_user_can('manage_options') 
                || !isset($_POST['produckConfigFirstSaveNonce'])
                || !wp_verify_nonce($_POST['produckConfigFirstSaveNonce'], 'produck_config_first_save')) {
            wp_send_json('Not authorised!', 401);
            wp_die();
        }

        $cid = intval( $_POST['cid'] );
        $answer = boolval( $_POST['answer'] );
        $option = get_option('produck_config');
        $option['customerId'] = $cid;
        $option['poweredByLinkAllowed'] = ($answer) ? 1 : 0;
        update_option('produck_config', $option);
        wp_die(); // this is required to terminate immediately and return a proper response
    }
}
?>
