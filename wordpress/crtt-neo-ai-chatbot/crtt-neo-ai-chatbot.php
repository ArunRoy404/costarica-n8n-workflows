<?php
/**
 * Plugin Name: Neo AI Chatbot - Costa Rica Transfers & Tours
 * Plugin URI: https://costaricatransfersandtours.com
 * Description: High-performance AI Chatbot assistant for Costa Rica Transfers & Tours, powered by n8n and React.
 * Version: 1.0.4
 * Author: Costa Rica Transfers & Tours
 * Author URI: https://costaricatransfersandtours.com
 * License: GPL v2 or later
 * Text Domain: crtt-neo-ai-chatbot
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('NEO_CHATBOT_VERSION', '1.0.4');
define('NEO_CHATBOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEO_CHATBOT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Enqueue frontend scripts and styles
 */
function neo_chatbot_enqueue_scripts() {
    // Check if enabled
    $is_enabled = get_option('neo_chatbot_enabled', '1');
    if ($is_enabled !== '1') {
        return;
    }

    // Google Fonts
    wp_enqueue_style(
        'neo-chatbot-fonts',
        'https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap',
        array(),
        null
    );

    // Widget CSS
    if (file_exists(NEO_CHATBOT_PLUGIN_DIR . 'assets/neo-chat-widget.css')) {
        wp_enqueue_style(
            'neo-chatbot-css',
            NEO_CHATBOT_PLUGIN_URL . 'assets/neo-chat-widget.css',
            array(),
            NEO_CHATBOT_VERSION
        );
    }

    // Widget JS
    if (file_exists(NEO_CHATBOT_PLUGIN_DIR . 'assets/neo-chat-widget.js')) {
        wp_enqueue_script(
            'neo-chatbot-js',
            NEO_CHATBOT_PLUGIN_URL . 'assets/neo-chat-widget.js',
            array(),
            NEO_CHATBOT_VERSION,
            true // Load in footer
        );

        // Pass settings to Javascript
        $prod_url = get_option('neo_chatbot_prod_url', 'https://ai.costaricatransfersandtours.com/webhook/neo');
        $test_url = get_option('neo_chatbot_test_url', 'https://ai.costaricatransfersandtours.com/webhook-test/neo');
        $default_mode = get_option('neo_chatbot_default_mode', 'live');
        $bot_name = get_option('neo_chatbot_bot_name', 'Neo AI');
        $greeting = get_option('neo_chatbot_greeting', 'Hola! I’m Neo, your dedicated Costa Rica tours & experiences specialist. How can I help you plan your perfect trip today?');

        $config = array(
            'productionUrl' => esc_url_raw($prod_url),
            'testUrl'       => esc_url_raw($test_url),
            'defaultMode'   => sanitize_text_field($default_mode),
            'botName'       => sanitize_text_field($bot_name),
            'greeting'      => sanitize_text_field($greeting),
            'pluginUrl'     => NEO_CHATBOT_PLUGIN_URL,
        );

        wp_localize_script('neo-chatbot-js', 'NeoChatbotConfig', $config);
    }
}
add_action('wp_enqueue_scripts', 'neo_chatbot_enqueue_scripts');

/**
 * Register Shortcode [neo_chat_button]
 * Allows inserting chat trigger buttons anywhere in content, Gutenberg, or Elementor
 */
function neo_chatbot_button_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text'  => 'Plan Your Trip with Neo AI',
        'class' => 'neo-chat-trigger-btn',
    ), $atts, 'neo_chat_button');

    return sprintf(
        '<button type="button" class="%s open-neo-chat" onclick="if(window.openNeoChatbot){window.openNeoChatbot();}">%s</button>',
        esc_attr($atts['class']),
        esc_html($atts['text'])
    );
}
add_shortcode('neo_chat_button', 'neo_chatbot_button_shortcode');

/**
 * Register Admin Settings
 */
function neo_chatbot_register_settings() {
    register_setting('neo_chatbot_settings_group', 'neo_chatbot_enabled', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '1',
    ));
    register_setting('neo_chatbot_settings_group', 'neo_chatbot_prod_url', array(
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => 'https://ai.costaricatransfersandtours.com/webhook/neo',
    ));
    register_setting('neo_chatbot_settings_group', 'neo_chatbot_test_url', array(
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => 'https://ai.costaricatransfersandtours.com/webhook-test/neo',
    ));
    register_setting('neo_chatbot_settings_group', 'neo_chatbot_default_mode', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'live',
    ));
    register_setting('neo_chatbot_settings_group', 'neo_chatbot_bot_name', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'Neo AI',
    ));
    register_setting('neo_chatbot_settings_group', 'neo_chatbot_greeting', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'Hola! I’m Neo, your dedicated Costa Rica tours & experiences specialist. How can I help you plan your perfect trip today?',
    ));
}
add_action('admin_init', 'neo_chatbot_register_settings');

/**
 * Add Admin Menu Item as a prominent top-level sidebar menu
 */
function neo_chatbot_add_admin_menu() {
    add_menu_page(
        'Neo AI Chatbot',
        'Neo AI Chatbot',
        'manage_options',
        'neo-ai-chatbot',
        'neo_chatbot_render_admin_page',
        'dashicons-format-chat',
        26
    );
}
add_action('admin_menu', 'neo_chatbot_add_admin_menu');

/**
 * Add 'Settings' action link on the Plugins list page
 */
function neo_chatbot_plugin_action_links($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=neo-ai-chatbot')) . '" style="font-weight:600; color:#18181b;">' . __('Settings', 'crtt-neo-ai-chatbot') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'neo_chatbot_plugin_action_links');

/**
 * Render Settings Page in WP Admin
 */
function neo_chatbot_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $enabled      = get_option('neo_chatbot_enabled', '1');
    $prod_url     = get_option('neo_chatbot_prod_url', 'https://ai.costaricatransfersandtours.com/webhook/neo');
    $test_url     = get_option('neo_chatbot_test_url', 'https://ai.costaricatransfersandtours.com/webhook-test/neo');
    $default_mode = get_option('neo_chatbot_default_mode', 'live');
    $bot_name     = get_option('neo_chatbot_bot_name', 'Neo AI');
    $greeting     = get_option('neo_chatbot_greeting', 'Hola! I’m Neo, your dedicated Costa Rica tours & transportation specialist. How can I help you plan your perfect trip today?');
    ?>
    <div class="wrap" style="max-width: 850px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="background: #18181b; color: #fff; padding: 24px 30px; border-radius: 14px; margin: 20px 0 25px 0; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: #27272a; display: flex; align-items: center; justify-content: center; font-size: 22px;">🤖</div>
                <div>
                    <h1 style="color: #fff; margin: 0; font-size: 22px; font-weight: 700;"><?php esc_html_e('Neo AI Chatbot Settings', 'crtt-neo-ai-chatbot'); ?></h1>
                    <p style="margin: 4px 0 0; color: #a1a1aa; font-size: 14px;"><?php esc_html_e('Costa Rica Transfers & Tours AI Assistant', 'crtt-neo-ai-chatbot'); ?></p>
                </div>
            </div>
        </div>

        <form method="post" action="options.php" style="background: #ffffff; padding: 30px; border-radius: 14px; border: 1px solid #e4e4e7; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
            <?php
            settings_fields('neo_chatbot_settings_group');
            do_settings_sections('neo_chatbot_settings_group');
            ?>

            <table class="form-table" role="presentation" style="margin-top: 0;">
                <tr>
                    <th scope="row" style="font-weight: 600; color: #09090b;"><?php esc_html_e('Chatbot Status', 'crtt-neo-ai-chatbot'); ?></th>
                    <td>
                        <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="neo_chatbot_enabled" value="1" <?php checked($enabled, '1'); ?> />
                            <span style="font-weight: 500; color: #18181b;"><?php esc_html_e('Enable floating chatbot on website', 'crtt-neo-ai-chatbot'); ?></span>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row" style="font-weight: 600; color: #09090b;"><?php esc_html_e('Bot Display Name', 'crtt-neo-ai-chatbot'); ?></th>
                    <td>
                        <input type="text" name="neo_chatbot_bot_name" value="<?php echo esc_attr($bot_name); ?>" class="regular-text" style="width: 100%; max-width: 480px;" />
                    </td>
                </tr>

                <tr>
                    <th scope="row" style="font-weight: 600; color: #09090b;"><?php esc_html_e('Initial Greeting Message', 'crtt-neo-ai-chatbot'); ?></th>
                    <td>
                        <textarea name="neo_chatbot_greeting" rows="3" class="large-text" style="width: 100%; max-width: 480px;"><?php echo esc_textarea($greeting); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row" style="font-weight: 600; color: #09090b;"><?php esc_html_e('Production Webhook URL', 'crtt-neo-ai-chatbot'); ?></th>
                    <td>
                        <input type="url" name="neo_chatbot_prod_url" value="<?php echo esc_attr($prod_url); ?>" class="regular-text code" style="width: 100%; max-width: 480px;" />
                        <p class="description"><?php esc_html_e('Default: https://ai.costaricatransfersandtours.com/webhook/neo', 'crtt-neo-ai-chatbot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row" style="font-weight: 600; color: #09090b;"><?php esc_html_e('Test Webhook URL', 'crtt-neo-ai-chatbot'); ?></th>
                    <td>
                        <input type="url" name="neo_chatbot_test_url" value="<?php echo esc_attr($test_url); ?>" class="regular-text code" style="width: 100%; max-width: 480px;" />
                        <p class="description"><?php esc_html_e('Default: https://ai.costaricatransfersandtours.com/webhook-test/neo', 'crtt-neo-ai-chatbot'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row" style="font-weight: 600; color: #09090b;"><?php esc_html_e('Default Mode', 'crtt-neo-ai-chatbot'); ?></th>
                    <td>
                        <select name="neo_chatbot_default_mode" style="min-width: 200px;">
                            <option value="live" <?php selected($default_mode, 'live'); ?>><?php esc_html_e('Live (Production)', 'crtt-neo-ai-chatbot'); ?></option>
                            <option value="test" <?php selected($default_mode, 'test'); ?>><?php esc_html_e('Test Mode', 'crtt-neo-ai-chatbot'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>

            <hr style="margin: 25px 0; border: none; border-top: 1px solid #e4e4e7;" />

            <div style="background: #f4f4f5; border-left: 4px solid #18181b; padding: 14px 18px; border-radius: 6px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 6px; font-size: 14px; font-weight: 600; color: #09090b;"><?php esc_html_e('💡 Pro-tip: Custom Chat Trigger Buttons', 'crtt-neo-ai-chatbot'); ?></h4>
                <p style="margin: 0; font-size: 13px; color: #52525b; line-height: 1.5;">
                    You can trigger the chat window from any link, button, or menu on your site by using:
                    <br />• <strong>Shortcode:</strong> <code>[neo_chat_button text="Plan Your Trip with Neo AI"]</code>
                    <br />• <strong>Link URL:</strong> <code>#open-neo-chat</code>
                    <br />• <strong>CSS Class:</strong> <code>open-neo-chat</code>
                </p>
            </div>

            <?php submit_button(__('Save Changes', 'crtt-neo-ai-chatbot'), 'primary', 'submit', false, array('style' => 'background: #18181b; border-color: #18181b; color: #fff; font-weight: 600; padding: 6px 20px; font-size: 14px; border-radius: 8px;')); ?>
        </form>
    </div>
    <?php
}
