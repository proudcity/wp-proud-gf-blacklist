<?php
/*
Plugin Name: WP Proud Gravity Forms Blacklist
Plugin URI: https://proudcity.com
Description: Blacklist individual emails or domains in Gravity Forms
Version: 2026.11.06.1
Author: ProudCity
Author URI: https://proudcity.com
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class Proudcity_GF_Blacklist
{
    private static $instance;

    /**
     * Spins up the instance of the plugin so that we don't get many instances running at once
     *
     * @since 1.0
     * @author SFNdesign, Curtis McHale
     *
     * @uses $instance->init()                      The main get it running function
     */
    public static function instance()
    {

        if (! self::$instance) {
            self::$instance = new Proudcity_GF_Blacklist();
            self::$instance->init();
        }

    } // instance

    /**
     * Spins up all the actions/filters in the plugin to really get the engine running
     *
     * @since 1.0
     * @author SFNdesign, Curtis McHale
     *
     * @uses $this->constants()                 Defines our constants
     * @uses $this->includes()                  Gets any includes we have
     */
    public function init()
    {

        $this->constants();
        $this->includes();

        add_filter('gform_entry_is_spam', array( $this, 'blacklist' ), 10, 3);

        // Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
        register_activation_hook(__FILE__, array( $this, 'activate' ));
        register_deactivation_hook(__FILE__, array( $this, 'deactivate' ));
        register_uninstall_hook(__FILE__, array( __CLASS__, 'uninstall' ));

    } // init

    /**
     * Checks fields for specified emails to blacklist them
     *
     * @since 2025.02.11
     * @author Curtis
     *
     * @param bool   $is_spam required   If the form has already been marked as spam
     * @param object $form    The whole form object
     * @param array  $entry   The form entry we're looknig at
     */
    public static function blacklist($is_spam, $form, $entry)
    {

        // already spam
        if ($is_spam) {
            return $is_spam;
        }

        // domains are defined in the site yaml deploy file
        // - name: PC_GF_DOMAIN_BLACKLIST
        //   value: domain.com, domain2.com
        $domains = array();

        if (getenv('PC_GF_DOMAIN_BLACKLIST')) {
            $domains = self::parse_list(getenv('PC_GF_DOMAIN_BLACKLIST'));
        }

        // domains are defined in the site yaml deploy file
        // - name: PC_GF_EMAIL_BLACKLIST
        //   value: email@domain.com, email@domain2.com
        $emails = array( 'spam@test.com' );

        if (getenv('PC_GF_EMAIL_BLACKLIST')) {
            $emails = self::parse_list(getenv('PC_GF_EMAIL_BLACKLIST'));
        }

        //update_option( 'sfn_test', rgar( $entry, '2' ) );
        //
        foreach ($form['fields'] as $field) {

            // skip admin fields or anything no email field type
            if ($field->is_administrative() || ! in_array($field->get_input_type(), array( 'email'))) {
                continue;
            }

            $field_value = strtolower(trim((string) $field->get_value_export($entry)));

            if ('' === $field_value) {
                continue;
            }

            if (in_array($field_value, $emails, true)) {
                return true;
            }

            // don't bother checking if the constant was never defined
            if (!empty($domains)) {
                // Check domain blacklist
                $parts = explode('@', $field_value);
                if (count($parts) === 2) {
                    $domain = $parts[1];
                    if (in_array($domain, $domains, true)) {
                        return true;
                    }
                }
            }
        } // foreach

        // if not caught before this the thing is not spam
        return false;

    } // blacklist

    /**
     * Turns a comma separated env value into a normalized list
     *
     * Trims and lowercases each entry and drops empties so "a@x.com, B@y.com,"
     * matches the same way as "a@x.com,b@y.com".
     *
     * @since 2026.11.06
     *
     * @param string $value Comma separated list from the environment
     * @return array
     */
    private static function parse_list($value)
    {
        $items = array_map('strtolower', array_map('trim', explode(',', $value)));

        return array_values(array_filter($items, 'strlen'));
    } // parse_list

    /**
     * Gives us any constants we need in the plugin
     *
     * @since 1.0
     */
    public function constants()
    {

        define('PC_GF_BLACK_PLUGIN_DIR', plugin_dir_path(__FILE__));

    }

    /**
     * Includes any externals
     *
     * @since 1.0
     * @author SFNdesign, Curtis McHale
     * @access public
     */
    public function includes()
    {

    }

    /**
     * Fired when plugin is activated
     *
     * @param   bool    $network_wide   TRUE if WPMU 'super admin' uses Network Activate option
     */
    public function activate($network_wide)
    {

    } // activate

    /**
     * Fired when plugin is deactivated
     *
     * @param   bool    $network_wide   TRUE if WPMU 'super admin' uses Network Activate option
     */
    public function deactivate($network_wide)
    {

    } // deactivate

    /**
     * Fired when plugin is uninstalled
     *
     * @param   bool    $network_wide   TRUE if WPMU 'super admin' uses Network Activate option
     */
    public function uninstall($network_wide)
    {

    } // uninstall

} // Proudcity_GF_Blacklist

Proudcity_GF_Blacklist::instance();
