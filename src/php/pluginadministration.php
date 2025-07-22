<?php
// prevent direct access
defined('ABSPATH') or die('Quidquid agis, prudenter agas et respice finem!');

/**
 * Produck Plugin Administration, where values of properties like customerId can be set by the plugin user.
 * The creation of this class followed this tutorial:
 * http://ottopress.com/2009/wordpress-settings-api-tutorial/
 */
class ProduckPluginAdministration
{
    private $translations;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'addPluginAdminPage'));
        add_action('admin_init', array($this, 'pluginAdminInit'));
        add_action('admin_head', function () {
            echo '<link rel="stylesheet" href="'
                . ProduckPlugin::getPluginUrl() . '/css/administration.min.css'
                . '" type="text/css" media="all" />';
        });

        add_action('wp_ajax_produck_first_config', array($this, 'handleFirstConfigAnswer'));

        $this->translations = ProduckPlugin::getTranslations(true, false, false);
    }

    public function getTranslation($section, $key)
    {
        if (isset($this->translations['translation'][$section][$key])) {
            return $this->translations['translation'][$section][$key];
        }
        return null;
    }

    /**
     * Contains the menu-building code.
     */
    public function addPluginAdminPage()
    {
        add_options_page(
            'ProDuck Einstellungen', // displayed in title tags
            'ProDuck', // text to be used for the menu
            'manage_options', //capability required for the menu to be displayed
            'produck-settings', // slug name used to refer to this menu (should be unique)
            array($this, 'createPluginOptionsPage')
        ); // callback function that will output the page content

        if (ProduckPlugin::isPoweredByLinkAllowed() < 0) {
            add_thickbox();
            add_action(
                'produck_administration_requested',
                array($this, 'showFirstConfigurationDialogue')
            );
        }
    }

    /**
     * Create the HTML output for the page (screen) displayed when the menu item is clicked.
     */
    public function createPluginOptionsPage()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        do_action('produck_administration_requested');

?>
        <div>
            <h2><?php $this->getTranslation('settings', 'produck_configuration') ?></h2>
            <?php $this->getTranslation('settings', 'plugin_settings_info') ?>
            <form action="options.php" method="post">
                <?php settings_fields('produck_options'); ?>
                <?php do_settings_sections('produck_settings_page'); ?>
                <input class="button button-primary" name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
            </form>
        </div>
    <?php
    }

    /**
     * Adds sections and field definitions.
     * For each section a callback is used to define a descriptive text. Sections are not necessary but just means to
     * logically group options/fields.
     * For each field a callback is used to define the actual HTML code for the field.
     */
    public function pluginAdminInit()
    {
        register_setting('produck_options', 'produck_config', array($this, 'validateProduckOptions'));

        add_settings_section('produck_settings_general', $this->getTranslation('settings', 'general'), array($this, 'generalSectionText'), 'produck_settings_page');
        add_settings_field('produckCustomerIdField', $this->getTranslation('settings', 'produck_user_id'), array($this, 'createCustomerIdInputField'), 'produck_settings_page', 'produck_settings_general');
        add_settings_field('produckDNSTokenField', 'DNS Token:', array($this, 'createDNSTokenInputField'), 'produck_settings_page', 'produck_settings_general');
        add_settings_field('produckQuackTokenField', 'Quack Token:', array($this, 'createQuackTokenInputField'), 'produck_settings_page', 'produck_settings_general');

        add_settings_section('produck_settings_quacks', $this->getTranslation('settings', 'post_options'), array($this, 'quacksSectionText'), 'produck_settings_page');
        add_settings_field('openQuackInNewPageField', $this->getTranslation('settings', 'open_posts_in_new_window'), array($this, 'createOpenQuackInNewPageField'), 'produck_settings_page', 'produck_settings_quacks');
        add_settings_field('maxQuackUrlTitleLengthField', $this->getTranslation('settings', 'max_title_length_in_url'), array($this, 'createMaxQuackUrlTitleLengthField'), 'produck_settings_page', 'produck_settings_quacks');
        add_settings_field('useThemeTemplateField', $this->getTranslation('settings', 'use_theme_template'), array($this, 'createUseThemeTemplateField'), 'produck_settings_page', 'produck_settings_quacks');
        add_settings_field('poweredByLinkAllowedField', $this->getTranslation('settings', 'show_provided_by_links'), array($this, 'createPoweredByLinkAllowedField'), 'produck_settings_page', 'produck_settings_quacks');

        add_settings_section('produck_settings_widget', 'Widget', array($this, 'widgetSectionText'), 'produck_settings_page');
        add_settings_field('numberOfQuacksShownField', $this->getTranslation('settings', 'max_number_of_posts'), array($this, 'createNumberOfQuacksShownField'), 'produck_settings_page', 'produck_settings_widget');

        add_settings_section('produck_settings_integration', 'Front Page Integration', array($this, 'integrationSectionText'), 'produck_settings_page');
        add_settings_field('combineProDuckAndLocalPostsArea', $this->getTranslation('settings', 'direct_integration'), array($this, 'createCombineProDuckAndLocalPostsArea'), 'produck_settings_page', 'produck_settings_integration');
        add_settings_field('displayShortCodeField', $this->getTranslation('settings', 'integration_per_short_code'), array($this, 'createShortCodeField'), 'produck_settings_page', 'produck_settings_integration');
        add_settings_field('ignoreDuplicatePosts', $this->getTranslation('settings', 'ignore_duplicate_posts'), array($this, 'createIgnoreDuplicatePostsField'), 'produck_settings_page', 'produck_settings_integration');
        add_settings_field('maxImplementationLoops', $this->getTranslation('settings', 'integration_loops'), array($this, 'createMaxImplementationLoopsInput'), 'produck_settings_page', 'produck_settings_integration');
        add_settings_field('ignorePostsWoPrimaryImage', $this->getTranslation('settings', 'ignore_noimage_posts'), array($this, 'createIgnorePostsWoImageField'), 'produck_settings_page', 'produck_settings_integration');
        add_settings_field('maxPostsToRecall', $this->getTranslation('settings', 'set_max_posts_to_recall'), array($this, 'createMaxPostsInput'), 'produck_settings_page', 'produck_settings_integration');

        add_settings_section('produck_settings_chat', $this->getTranslation('settings', 'chat_options'), array($this, 'chatSectionText'), 'produck_settings_page');
        add_settings_field('chatEnabledField', $this->getTranslation('settings', 'chat_enabled'), array($this, 'createChatEnabledField'), 'produck_settings_page', 'produck_settings_chat');
    }

    /**
     * Defines the description for the section 'general settings'.
     */
    public function generalSectionText()
    {
        echo $this->getTranslation('settings', 'settings_intro_general');
    }

    /**
     * Defines the description for the section 'chat settings'.
     */
    public function chatSectionText()
    {
        echo $this->getTranslation('settings', 'settings_intro_chat');
    }

    /**
     * Defines the description for the section 'quacks settings'.
     */
    public function quacksSectionText()
    {
        echo $this->getTranslation('settings', 'settings_intro_links');
    }

    /**
     * Defines the description for the section 'widget settings'.
     */
    public function widgetSectionText()
    {
        echo $this->getTranslation('settings', 'settings_intro_widget');
    }

    /**
     * Defines the description for the section 'integration settings'.
     */
    public function integrationSectionText()
    {
        echo $this->getTranslation('settings', 'settings_intro_integration_options');
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'customerId'.
     */
    public function createCustomerIdInputField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createCustomerIdInputField ' . print_r($options['customerId'], true));
        echo '<input type="text" id="produckCustomerIdField" name="produck_config[customerId]" size="10" value="' . $options['customerId'] . '"/>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'customerId'.
     */
    public function createDNSTokenInputField()
    {
        $options = get_option('produck_config');
        $token = isset($options['dnsToken']) ? $options['dnsToken'] : '';
    
        error_log('Settings: createDNSTokenInputField – token=' . print_r($token, true));
    
        ?>
        <div id="produckDNSTokenContainer">
            <div class="token-field">
                <input 
                    type="text" 
                    id="produckDNSTokenField" 
                    name="produck_config[dnsToken]" 
                    size="50" 
                    value="<?= esc_attr($token) ?>" 
                />
            </div>
        </div>
        <?php
    }


    /**
     * Creates the HTML-code for the input elements that lets the user enter up to "maxTokens" values for the option-value 'quackToken'.
     */
    public function createQuackTokenInputField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createQuackTokenInputField ' . print_r($options['quackToken']['quackTokens'], true));
        $tokens = isset($options['quackToken']['quackTokens']) ? $options['quackToken']['quackTokens'] : array();

    ?>
        <div id="produckQuackTokenContainer">
            <?php
            if (!empty($tokens)) {
                foreach ($tokens as $index => $token) {
                    echo '<div class="token-field"><input type="text" id="produckQuackTokenField_' . $index . '" name="produck_config[quackTokens][]" size="50" value="' . esc_attr($token) . '"/>';
                    if ($index > 0) {
                        echo '<button type="button" class="deleteTokenButton">Delete</button>';
                    }
                    echo '</div>';
                }
            } else {
                echo '<div class="token-field"><input type="text" id="produckQuackTokenField_0" name="produck_config[quackTokens][]" size="50" value=""/></div>';
            }
            ?>
        </div>
        <button type="button" id="addQuackTokenButton"><?php esc_html_e('Add another token', 'textdomain'); ?></button>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var container = document.getElementById('produckQuackTokenContainer');
                var addButton = document.getElementById('addQuackTokenButton');
                var maxTokens = 20;

                addButton.addEventListener('click', function() {
                    var tokenFields = container.getElementsByClassName('token-field');
                    var tokenCount = tokenFields.length;

                    if (tokenCount < maxTokens) {
                        var newTokenField = document.createElement('div');
                        newTokenField.classList.add('token-field');
                        newTokenField.innerHTML = '<input type="text" id="produckQuackTokenField_' + tokenCount + '" name="produck_config[quackTokens][]" size="50" value=""/><button type="button" class="deleteTokenButton">Delete</button>';
                        container.appendChild(newTokenField);
                    } else {
                        alert('<?php esc_html_e('You have reached the maximum number of tokens.', 'textdomain'); ?>');
                    }
                });

                container.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('deleteTokenButton')) {
                        var tokenFields = container.getElementsByClassName('token-field');
                        if (tokenFields.length > 1) {
                            e.target.parentElement.remove();
                        } else {
                            alert('<?php esc_html_e('You must have at least one token.', 'textdomain'); ?>');
                        }
                    }
                });
            });
        </script>
    <?php
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'chatEnabled'.
     */
    public function createChatEnabledField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createChatEnabledField ' . print_r($options['chatEnabled'], true));

        echo '<select id="chatEnabledField" name="produck_config[chatEnabled]">';
        echo ' <option value="1"' . (($options['chatEnabled']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'yes') . '</option>';
        echo ' <option value="0"' . ((!$options['chatEnabled']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'no') . '</option>';
        echo '</select>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'openQuackInNewPage'.
     */
    public function createOpenQuackInNewPageField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createOpenQuackInNewPageField ' . print_r($options['openQuackInNewPage'], true));

        echo '<select id="openQuackInNewPageField" name="produck_config[openQuackInNewPage]">';
        echo ' <option value="1"' . (($options['openQuackInNewPage']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'yes') . '</option>';
        echo ' <option value="0"' . ((!$options['openQuackInNewPage']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'no') . '</option>';
        echo '</select>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'maxQuackUrlTitleLength'.
     */
    public function createMaxQuackUrlTitleLengthField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createMaxQuackUrlTitleLengthField ' . print_r($options['maxQuackUrlTitleLength'], true));

        echo '<input id="maxQuackUrlTitleLengthField" name="produck_config[maxQuackUrlTitleLength]" size="10" type="text" value="' . $options['maxQuackUrlTitleLength'] . '"/>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user enter a value for the option-value 'numberOfQuacksShown'.
     */
    public function createNumberOfQuacksShownField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createNumberOfQuacksShownField ' . print_r($options['numberOfQuacksShown'], true));

        echo '<input id="numberOfQuacksShownField" name="produck_config[numberOfQuacksShown]" size="10" type="text" value="' . $options['numberOfQuacksShown'] . '"/>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user decide whether to use the plugins built in
     * template for quack pages or the default theme page-template.
     */
    public function createUseThemeTemplateField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createUseThemeTemplateField ' . print_r($options['useThemeTemplate'], true));

        echo '<select id="useThemeTemplateField" name="produck_config[useThemeTemplate]">';
        echo ' <option value="1"' . (($options['useThemeTemplate']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'yes') . '</option>';
        echo ' <option value="0"' . ((!$options['useThemeTemplate']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'no') . '</option>';
        echo '</select>';
    }

    /**
     * Creates a selection to choose whether to show produck posts just in the main query or in every post related query
     */
    public function createCombineProDuckAndLocalPostsArea()
    {
        $options = get_option('produck_config');
        error_log('Settings: combineProduckPosts ' . print_r($options['combineProduckPosts'], true));

        echo '<p>' . $this->getTranslation('settings', 'settings_intro_integration') . '</p>';
        echo '<ol>';
        echo '<li><i><b>' . $this->getTranslation('settings', 'combine_produck_posts_main_area') . ':</b> ' . $this->getTranslation('settings', 'integration_options_text_main_area') . '</i></li>';
        echo '<li><i><b>' . $this->getTranslation('settings', 'combine_produck_posts_all_areas') . ':</b> ' . $this->getTranslation('settings', 'integration_options_text_all_areas') . '</i></li>';
        echo '<li><i><b>' . $this->getTranslation('settings', 'combine_produck_posts_redirect') . ':</b> ' . $this->getTranslation('settings', 'integration_options_text_redirect') . '</i></li>';
        echo '</ol>';

        echo '<select id="combineProDuckAndLocalPostsArea" name="produck_config[combineProduckPosts]">';
        echo ' <option value="NoIntegration"' . (($options['combineProduckPosts'] == 'NoIntegration') ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'combine_produck_posts_no_integration') . '</option>';
        echo ' <option value="integrateInMainQuery"' . (($options['combineProduckPosts'] == 'integrateInMainQuery') ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'combine_produck_posts_main_area') . '</option>';
        echo ' <option value="integrateInAllQueries"' . (($options['combineProduckPosts'] == 'integrateInAllQueries') ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'combine_produck_posts_all_areas') . '</option>';
        echo ' <option value="integratePerRedirect"' . (($options['combineProduckPosts'] == 'integratePerRedirect') ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'combine_produck_posts_redirect') . '</option>';
        echo '</select>';
    }

    /**
     * Creates the HTML-code for the input element that lets the user decide whether to ignore posts without a primary image.
     */
    public function createIgnorePostsWoImageField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createIgnorePostsWoImageField ' . print_r($options['ignorePostsWoPrimaryImage'], true));

        echo '<select id="ignorePostsWoPrimaryImageField" name="produck_config[ignorePostsWoPrimaryImage]">';
        echo ' <option value="1"' . (($options['ignorePostsWoPrimaryImage']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'yes') . '</option>';
        echo ' <option value="0"' . ((!$options['ignorePostsWoPrimaryImage']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'no') . '</option>';
        echo '</select>';
    }

    /**
     * Creates the HTML-code for the input element that lets the user decide whether to ignore duplicate posts.
     */
    public function createIgnoreDuplicatePostsField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createIgnoreDuplicatePostsField ' . print_r($options['ignoreDuplicatePosts'], true));

        echo '<select id="ignoreDuplicatePostsField" name="produck_config[ignoreDuplicatePosts]">';
        echo ' <option value="1"' . (($options['ignoreDuplicatePosts']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'yes') . '</option>';
        echo ' <option value="0"' . ((!$options['ignoreDuplicatePosts']) ? 'selected="selected"' : '') . '>' . $this->getTranslation('settings', 'no') . '</option>';
        echo '</select>';
    }

    /**
     * Creates the HTML-code for the input element that limits the loops for post implementation to limit duplicate posts.
     */
    public function createMaxImplementationLoopsInput()
    {
        $options = get_option('produck_config');
        error_log('Settings: maxImplementationLoopsInput ' . print_r($options['maxImplementationLoops'], true));

        echo '<p>' . $this->getTranslation('settings', 'set_post_loop_limit') . '</p><br/>';
        echo '<input id="maxImplementationLoopsInput" name="produck_config[maxImplementationLoops]" size="10" type="number" min="1" value="' . esc_attr($options['maxImplementationLoops']) . '"/>';
    }

    /**
     * Creates the HTML-code for the input element that lets the user enter a maximum number of posts to fetch.
     */
    public function createMaxPostsInput()
    {
        $options = get_option('produck_config');
        error_log('Settings: maxPostsToRecallField ' . print_r($options['maxPostsToRecall'], true));

        echo '<input id="maxPostsToRecallField" name="produck_config[maxPostsToRecall]" size="10" type="number" min="1" value="' . esc_attr($options['maxPostsToRecall']) . '"/>';
    }

    /**
     * Acivates redirect front page to combined produck+wordpress article overview
     */
    public function createShortCodeField()
    {
        $shortcode_example = '[combined_posts]';
        echo '<p><i>' . $this->getTranslation('settings', 'integration_per_short_code_description') . '</i></p>';
        echo '<pre>' . esc_attr($shortcode_example) . '</pre>';
    }

    /**
     * Creates the HTML-code for the input element that let's the user decide whether to show powered-by-links or not.
     */
    public function createPoweredByLinkAllowedField()
    {
        $options = get_option('produck_config');
        error_log('Settings: createPoweredByLinkAllowedField ' . print_r($options['poweredByLinkAllowed'], true));

        echo '<select id="poweredByLinkAllowedField" name="produck_config[poweredByLinkAllowed]">';
        if ($options['poweredByLinkAllowed'] > 0) {
            echo ' <option value="1" selected="selected">' . $this->getTranslation('settings', 'yes') . '</option>';
            echo ' <option value="0">' . $this->getTranslation('settings', 'no') . '</option>';
        } else {
            echo ' <option value="1">' . $this->getTranslation('settings', 'yes') . '</option>';
            echo ' <option value="0" selected="selected">' . $this->getTranslation('settings', 'no') . '</option>';
        }
        echo '</select>';
    }

    private function sanitizeQuackTokensInput($input)
    {
        $trustedInput = array();

        if (isset($input['quackTokens']) && is_array($input['quackTokens'])) {
            $trustedInput['quackTokens'] = array();

            foreach ($input['quackTokens'] as $index => $token) {
                // Trim whitespace and leading zeros off the quack token
                $trimmedToken = trim($token);

                // Validate the token to ensure it is a hex string
                if (preg_match('/^[0-9a-fA-F]+$/i', $trimmedToken)) {
                    $trustedInput['quackTokens'][] = $trimmedToken;
                } else {
                    add_settings_error(
                        'produck_config',
                        'produckQuackTokenField_' . $index,
                        $this->getTranslation('settings', 'quack_token_must_be_hex')
                    );
                }
            }
        } else {
            add_settings_error(
                'produck_config',
                'produckQuackTokenField',
                $this->getTranslation('settings', 'quack_token_must_be_array')
            );
        }

        return $trustedInput;
    }   

    /**
     * Validate the user's input on the settings page. The validated input will be returned and then stored
     * in the database by the settings-API.
     */
    public function validateProduckOptions($input)
    {
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
        if (preg_match('/^[1-9][0-9]{0,31}$/', $inCustomerId)) {
            $trustedInput['customerId'] = $inCustomerId;
        } else {
            add_settings_error(
                'produck_config',
                'produckCustomerIdField',
                $this->getTranslation('settings', 'user_id_must_be_integer')
            );
        }

        $dnsToken = isset($input['dnsToken']) ? trim($input['dnsToken']) : '';
        if (preg_match('/^[A-Za-z0-9\-_]{30,45}$/', $dnsToken)) {
            $trustedInput['dnsToken'] = $dnsToken;
        } else {
            add_settings_error(
                'produck_config',
                'produckDNSTokenField',
                $this->getTranslation('settings', 'quack_dnstoken_must_be_valid_token')
            );
        }

        // trim whitespace off the quack token
        $trustedInput['quackToken'] = self::sanitizeQuackTokensInput($input);

        $inChatEnabled = isset($input['chatEnabled']) ? trim($input['chatEnabled']) : '';
        if (preg_match('/^[0|1]$/i', $inChatEnabled)) {
            $trustedInput['chatEnabled'] = $inChatEnabled;
        }

        $inopenQuackInNewPage = isset($input['openQuackInNewPage']) ? trim($input['openQuackInNewPage']) : '';
        if (preg_match('/^[0|1]$/i', $inopenQuackInNewPage)) {
            $trustedInput['openQuackInNewPage'] = $inopenQuackInNewPage;
        }

        $maxQuackUrlTitleLength = isset($input['maxQuackUrlTitleLength']) ? trim($input['maxQuackUrlTitleLength']) : '';
        if (preg_match('/^[1-9][0-9]{0,31}$/', $maxQuackUrlTitleLength)) {
            $trustedInput['maxQuackUrlTitleLength'] = $maxQuackUrlTitleLength;
        } else {
            add_settings_error(
                'produck_config',
                'maxQuackUrlTitleLengthField',
                $this->getTranslation('settings', 'title_length_must_be_integer')
            );
        }

        $inNumberOfQuacksShown = isset($input['numberOfQuacksShown']) ? trim($input['numberOfQuacksShown']) : '';
        if (preg_match('/^[1-9][0-9]{0,31}$/', $inNumberOfQuacksShown)) {
            $trustedInput['numberOfQuacksShown'] = $inNumberOfQuacksShown;
        } else {
            add_settings_error(
                'produck_config',
                'numberOfQuacksShownField',
                $this->getTranslation('settings', 'number_of_quacks_must_be_integer')
            );
        }

        $useThemeTemplate = isset($input['useThemeTemplate']) ? trim($input['useThemeTemplate']) : '';
        if (preg_match('/^[0|1]$/i', $useThemeTemplate)) {
            $trustedInput['useThemeTemplate'] = $useThemeTemplate;
        }

        // Validate combineProduckPosts
        $combineProDuckPosts = isset($input['combineProduckPosts']) ? trim($input['combineProduckPosts']) : '';
        if (in_array($combineProDuckPosts, ['NoIntegration', 'integrateInMainQuery', 'integrateInAllQueries', 'integratePerRedirect'])) {
            $trustedInput['combineProduckPosts'] = $combineProDuckPosts;
        } else {
            // Default to 'NoIntegration' if invalid
            $trustedInput['combineProduckPosts'] = 'NoIntegration';
        }

        // Validation for "Ignore Posts without Primary Image"
        $ignorePostsWoPrimaryImage = isset($input['ignorePostsWoPrimaryImage']) ? trim($input['ignorePostsWoPrimaryImage']) : '';
        if (preg_match('/^[0|1]$/', $ignorePostsWoPrimaryImage)) {
            $trustedInput['ignorePostsWoPrimaryImage'] = $ignorePostsWoPrimaryImage;
        }

        // Validation for "Ignore Duplicate Posts"
        $ignoreDuplicatePosts = isset($input['ignoreDuplicatePosts']) ? trim($input['ignoreDuplicatePosts']) : '';
        if (preg_match('/^[0|1]$/', $ignoreDuplicatePosts)) {
            $trustedInput['ignoreDuplicatePosts'] = $ignoreDuplicatePosts;
        }

        // Validation for "Set Max Loops for Post Implementation Runs"
        $setMaxImplementationLoops = isset($input['maxImplementationLoops']) ? trim($input['maxImplementationLoops']) : 1;
        if (preg_match('/^[1-9][0-9]{0,31}$/', $setMaxImplementationLoops)) {
            $trustedInput['maxImplementationLoops'] = $setMaxImplementationLoops;
        } else {
            add_settings_error(
                'produck_config',
                'maxImplementationLoops',
                $this->getTranslation('settings', 'max_loops_must_be_integer')
            );
        }

        // Validation for "Set Max Posts to Recall"
        $setMaxPostsToRecall = isset($input['maxPostsToRecall']) ? trim($input['maxPostsToRecall']) : 10;
        if (preg_match('/^[1-9][0-9]{0,31}$/', $setMaxPostsToRecall)) {
            $trustedInput['maxPostsToRecall'] = $setMaxPostsToRecall;
        } else {
            add_settings_error(
                'produck_config',
                'maxPostsToRecall',
                $this->getTranslation('settings', 'max_posts_must_be_integer')
            );
        }

        $poweredByLinkAllowed = isset($input['poweredByLinkAllowed']) ? trim($input['poweredByLinkAllowed']) : '';
        if (preg_match('/^[0|1]$/i', $poweredByLinkAllowed)) {
            $trustedInput['poweredByLinkAllowed'] = $poweredByLinkAllowed;
        }

        return $trustedInput;
    }

    public function showFirstConfigurationDialogue()
    {
        // inject a script that opens a thickbox to ask for the user's permission to show powered-by-links
    ?>
        <script>
            jQuery(window).load(function($) {
                tb_show(<?php $this->getTranslation('settings', 'produck_configuration') ?>, "#TB_inline?width=400&height=180&inlineId=produckFirstConfigDialogue", null);
            });

            function produck_sendFirstConfigAnswer() {
                var customerIdField = document.getElementById('produckFirstConfigCustomerIdField');
                var cid = customerIdField.value;
                var poweredByField = document.getElementById('produckFirstConfigPoweredByLinkAnswerField');
                var powby = poweredByField.value;

                var feedback;
                if (isNaN(cid) || cid < 1) {
                    feedback = <?php $this->getTranslation('settings', 'invalid_user_id') ?>;
                }

                if (isNaN(powby) || powby != 0 && powby != 1) {
                    if (feedback) {
                        feedback += <?php $this->getTranslation('settings', 'invalid_user_id') . ' plus ' .  $this->getTranslation('settings', 'invalid_response') ?> "Antwort ungültig";
                    } else {
                        feedback = <?php $this->getTranslation('settings', 'invalid_response') ?>;
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
                    <?php $this->getTranslation('settings', 'produck_id_needed') ?>
                </div>
                <div class="label-cell-cid">
                    <?php $this->getTranslation('settings', 'produck_user_id_label') ?>
                </div>
                <div class="value-cell-cid">
                    <input type="text" id="produckFirstConfigCustomerIdField" />
                </div>
                <div class="label-cell-powby">
                    <?php $this->getTranslation('settings', 'support_produck_links') ?>
                </div>
                <div class="value-cell-powby">
                    <select id="produckFirstConfigPoweredByLinkAnswerField">
                        <option value="1" selected="selected">Okay</option>
                        <option value="0"><?php $this->getTranslation('settings', 'no') ?></option>
                    </select>
                </div>
                <div id="produckFirstConfigFeedback" class="feedback-cell">
                </div>
                <div class="button-cell">
                    <button class="button button-primary" onclick="javascript:produck_sendFirstConfigAnswer();"><?php $this->getTranslation('settings', 'save') ?></button>
                    <button class="button" onclick="javascript:tb_remove();return false;"><?php $this->getTranslation('settings', 'cancel') ?></button>
                    <?php
                    wp_nonce_field('produck_config_first_save', 'produckConfigFirstSaveNonce');
                    ?>
                </div>
            </div>
        </div>
<?php
    }

    public function handleFirstConfigAnswer()
    {
        if (
            !current_user_can('manage_options')
            || !isset($_POST['produckConfigFirstSaveNonce'])
            || !wp_verify_nonce($_POST['produckConfigFirstSaveNonce'], 'produck_config_first_save')
        ) {
            wp_send_json('Not authorised!', 401);
            wp_die();
        }

        $cid = intval($_POST['cid']);
        $answer = boolval($_POST['answer']);
        $option = get_option('produck_config');
        $option['customerId'] = $cid;
        $option['poweredByLinkAllowed'] = ($answer) ? 1 : 0;
        update_option('produck_config', $option);
        wp_die(); // this is required to terminate immediately and return a proper response
    }
}
?>