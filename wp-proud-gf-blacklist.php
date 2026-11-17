<?php
/*
Plugin Name: WP Proud Gravity Forms Blacklist
Plugin URI: https://proudcity.com
Description: Blacklist individual emails, domains, IPs or countries in Gravity Forms
Version: 2026.11.17.1
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

        // IP and country checks, allowlisted IPs skip these but not the email/domain checks
        $ip          = self::get_submitter_ip($entry);
        $allowlisted = '' !== $ip && self::ip_in_list($ip, self::parse_list((string) getenv('PC_GF_IP_ALLOWLIST')));

        if (! $allowlisted) {
            // - name: PC_GF_IP_BLACKLIST
            //   value: 203.0.113.7,198.51.100.0/24,2001:db8::/32
            if ('' !== $ip && self::ip_in_list($ip, self::parse_list((string) getenv('PC_GF_IP_BLACKLIST')))) {
                self::flag_spam($form, 'IP blacklist');
                return true;
            }

            // - name: PC_GF_ALLOWED_COUNTRIES
            //   value: US
            $allowed_countries = self::get_allowed_countries();

            if (! empty($allowed_countries)) {
                $country = self::get_request_country();

                if (null !== $country && ! in_array($country, $allowed_countries, true)) {
                    // forms that don't store IPs don't get the location stored either
                    $reason = rgars($form, 'personalData/preventIP')
                        ? 'Country not allowed'
                        : 'Country not allowed (' . $country . ')';

                    self::flag_spam($form, $reason);
                    return true;
                }
            }
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
     * Records which rule marked the entry as spam in the Gravity Forms spam note
     *
     * Never pass the IP in the reason, forms with "Prevent the storage of IP addresses"
     * opted out of keeping it.
     *
     * @since 2026.11.17
     *
     * @param array  $form   The form object
     * @param string $reason Short description of the rule that fired
     */
    private static function flag_spam($form, $reason)
    {
        if (class_exists('GFCommon') && method_exists('GFCommon', 'set_spam_filter')) {
            GFCommon::set_spam_filter(rgar($form, 'id'), 'PC GF Blacklist', $reason);
        }
    } // flag_spam

    /**
     * Gets the submitter IP from the entry, falling back to the request IP
     *
     * Forms with "Prevent the storage of IP addresses" leave the entry ip empty.
     *
     * @since 2026.11.17
     *
     * @param array $entry The form entry
     * @return string Valid IP address or an empty string when unknown
     */
    private static function get_submitter_ip($entry)
    {
        $ip = trim((string) rgar($entry, 'ip'));

        if ('' === $ip && class_exists('GFFormsModel')) {
            $ip = trim((string) GFFormsModel::get_ip());
        }

        if (false === filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        return $ip;
    } // get_submitter_ip

    /**
     * Gets the allowed country codes from PC_GF_ALLOWED_COUNTRIES
     *
     * Keeps only two letter codes. If the variable is set but nothing valid is
     * left ("USA", "US CA") the check is turned off instead of sending every
     * entry to spam, and the problem is logged.
     *
     * @since 2026.11.17
     *
     * @return array Lowercase two letter codes, empty when the check is off
     */
    private static function get_allowed_countries()
    {
        $items = self::parse_list((string) getenv('PC_GF_ALLOWED_COUNTRIES'));

        $valid = array_values(array_filter($items, function ($code) {
            return 1 === preg_match('/^[a-z]{2}$/', $code);
        }));

        if (count($valid) !== count($items)) {
            error_log('wp-proud-gf-blacklist: ignoring invalid PC_GF_ALLOWED_COUNTRIES entries: ' . implode(',', array_diff($items, $valid)));
        }

        return $valid;
    } // get_allowed_countries

    /**
     * Gets the visitor country set by the ingress in X-PC-Country
     *
     * The ingress replaces any client supplied value. Anything that is not two
     * letters ("--", "-", empty, missing) is unknown and unknown is allowed.
     *
     * @since 2026.11.17
     *
     * @return string|null Lowercase two letter country code or null when unknown
     */
    private static function get_request_country()
    {
        if (! isset($_SERVER['HTTP_X_PC_COUNTRY'])) {
            return null;
        }

        $country = strtolower(trim((string) $_SERVER['HTTP_X_PC_COUNTRY']));

        if (! preg_match('/^[a-z]{2}$/', $country)) {
            return null;
        }

        return $country;
    } // get_request_country

    /**
     * Checks if an IP matches any IP or CIDR in the list
     *
     * @since 2026.11.17
     *
     * @param string $ip   The IP to check
     * @param array  $list IPs and CIDR ranges
     * @return bool
     */
    private static function ip_in_list($ip, array $list)
    {
        foreach ($list as $rule) {
            if (self::ip_matches($ip, $rule)) {
                return true;
            }
        }

        return false;
    } // ip_in_list

    /**
     * Checks an IP against a single IP or CIDR rule, IPv4 or IPv6
     *
     * Compares packed binary addresses so differently written IPv6 forms match.
     * Different families never match and invalid rules are ignored.
     *
     * @since 2026.11.17
     *
     * @param string $ip   The IP to check
     * @param string $rule An IP or CIDR range
     * @return bool
     */
    private static function ip_matches($ip, $rule)
    {
        $parts   = explode('/', $rule, 2);
        $ip_bin  = self::to_ipv4_if_mapped(@inet_pton($ip));
        $net_bin = @inet_pton($parts[0]);

        if (false === $ip_bin || false === $net_bin) {
            return false;
        }

        if (1 === count($parts)) {
            $net_bin = self::to_ipv4_if_mapped($net_bin);

            return strlen($ip_bin) === strlen($net_bin) && $ip_bin === $net_bin;
        }

        if (strlen($ip_bin) !== strlen($net_bin) || ! ctype_digit($parts[1])) {
            return false;
        }

        $prefix = (int) $parts[1];

        // ranges broader than /8 (IPv4) or /16 (IPv6) are almost always a typo
        // and would match most of the internet, so they are ignored
        $min_prefix = 4 === strlen($net_bin) ? 8 : 16;

        if ($prefix < $min_prefix || $prefix > strlen($net_bin) * 8) {
            return false;
        }

        $bytes = intdiv($prefix, 8);

        if (substr($ip_bin, 0, $bytes) !== substr($net_bin, 0, $bytes)) {
            return false;
        }

        $remainder = $prefix % 8;

        if (0 === $remainder) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ip_bin[$bytes]) & $mask) === (ord($net_bin[$bytes]) & $mask);
    } // ip_matches

    /**
     * Turns a packed IPv4-mapped IPv6 address (::ffff:1.2.3.4) into packed IPv4
     *
     * So a mapped address still matches IPv4 rules.
     *
     * @since 2026.11.17
     *
     * @param string|false $bin Packed address from inet_pton()
     * @return string|false
     */
    private static function to_ipv4_if_mapped($bin)
    {
        if (is_string($bin) && 16 === strlen($bin) && str_repeat("\0", 10) . "\xff\xff" === substr($bin, 0, 12)) {
            return substr($bin, 12);
        }

        return $bin;
    } // to_ipv4_if_mapped

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
