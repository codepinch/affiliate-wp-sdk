<?php

/**
  Plugin Name: CodePinch Affiliate Sample
  Description: Check CodePinch plugin status and show notification if it is not installed
  Version: 1.0
  Author: Vasyl Martyniuk <vasyl@vasyltech.com>
  Author URI: https://www.vasyltech.com

  -------
  LICENSE: This file is subject to the terms and conditions defined in
  file 'license.txt', which is part of Advanced Access Manager source package.
 *
 */

/**
 * Main plugin's class
 * 
 * @package CodePinchChecker
 * @author Vasyl Martyniuk <vasyl@vasyltech.com>
 */
class CodePinchAffiliateSample {

    /**
     * Single instance of itself
     *
     * @var CodePinchAffiliateSample
     *
     * @access private
     */
    private static $_instance = null;

    /**
     * Initialize the CodePinchAffiliateSample Object
     *
     * @return void
     *
     * @access protected
     */
    protected function __construct() {
        if (is_admin()) {
            if (is_multisite() && is_network_admin()) {
                add_action('network_admin_notices', array($this, 'notification'));
                add_action('network_admin_menu', array($this, 'adminMenu'));
            } elseif (!is_multisite()) {
                add_action('admin_notices', array($this, 'notification'));
                add_action('admin_menu', array($this, 'adminMenu'));
            }
            
            //print required CSS
            add_action('admin_print_styles', array($this, 'printStylesheet'));
            
            //initialize CodePinch affiliate
            CodePinch_Affiliate::boostrap();
        }
    }
    
    /**
     * Dashboard notification
     * 
     * Show "Install CodePinch Plugin" notification but only when it is actually
     * not installed.
     * 
     * @return void
     * 
     * @access public
     */
    public function notification() {
        if (!CodePinch_Affiliate::isInstalled() && !CodePinch_Affiliate::isPageUI()) {
            $style = 'padding: 10px; font-weight: 700; letter-spacing:0.5px;';
            $url   = CodePinch_Affiliate::getUrl(AFFILIATE_SAMPLE_CODE);
            
            echo '<div class="updated notice"><p style="' . $style . '">';
            echo 'Improve your website performance and security. ';
            echo '<a href="' . $url . '">Install CodePinch Plugin.</a>';
            echo '</p></div>';
        }
    }
    
    /**
     * Register Admin Menu
     *
     * @return void
     *
     * @access public
     */
    public function adminMenu() {
        //register the menu
        add_menu_page(
            'Affiliate', 
            'Affiliate', 
            'administrator', 
            'codepinch-affiliate-sample', 
            array($this, 'renderPage')
        );
    }
    
    /**
     * Render Main Content page
     *
     * @return void
     *
     * @access public
     */
    public function renderPage() {
        ob_start();
        require_once(dirname(__FILE__) . '/phtml/index.phtml');
        $content = ob_get_contents();
        ob_end_clean();

        echo $content;
    }
    
    /**
     * Print necessary styles
     *
     * @return void
     *
     * @access public
     */
    public function printStylesheet() {
        if (filter_input(INPUT_GET, 'page') == 'codepinch-affiliate-sample') {
            wp_enqueue_style(
                    'cpas', plugins_url('/css', __FILE__) . '/bootstrap.min.css'
            );
        }
    }

    /**
     * Initialize the CodePinchAffiliateSample plugin
     *
     * @return CodePinchAffiliateSample
     *
     * @access public
     * @static
     */
    public static function getInstance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Plugin activation hook
     * 
     * @return void
     * 
     * @access public
     */
    public static function activate() {
        global $wp_version;
        
        //check PHP Version
        if (version_compare(PHP_VERSION, '5.2') == -1) {
            exit(__('PHP 5.2 or higher is required.'));
        } elseif (version_compare($wp_version, '3.8') == -1) {
            exit(__('WP 3.8 or higher is required.'));
        }
    }

}

if (defined('ABSPATH')) {
    //request CodePinch affiliate SDK
    require_once dirname(__FILE__) . '/vendor/CodePinch_Affiliate.php';
    
    define('AFFILIATE_SAMPLE_CODE', 'TEST');
    
    //init hook
    add_action('init', 'CodePinchAffiliateSample::getInstance');
    
    //activation & deactivation hooks
    register_activation_hook(__FILE__, 'CodePinchAffiliateSample::activate');
}