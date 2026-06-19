<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Class for add monobank to wordpress menu
 * 
 * */
Class MorkvaMonopayMenu
{
    /**
     * Slug for page in Woo Tab Sections
     * 
     * */
    public $slug = 'admin.php?page=wc-settings&tab=checkout&section=morkva-monopay';

    /**
     * Constructor for create menu
     * 
     * */
    public function __construct()
    {
        # Add menu
        add_action('admin_menu', array($this, 'mrkv_mono_register_admin_menu'));
    }

    /**
     * Register menu page
     * 
     * */
    public function mrkv_mono_register_admin_menu()
    {
        # Add menu Monopay
        add_menu_page('morkva plata', 'morkva plata', 'manage_options', $this->slug, false, plugin_dir_url(__DIR__) . 'assets/images/morkva-icon-20x20.svg', 26);

        # Add menu Monopay Acquiring
        add_submenu_page($this->slug, __('Acquiring', 'morkva-monobank-extended'), __('Acquiring', 'morkva-monobank-extended'), 'manage_options', $this->slug); 
    }
}