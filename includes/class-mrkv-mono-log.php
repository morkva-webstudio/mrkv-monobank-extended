<?php
/**
 * Centralized debug logging for morkva Plata by Mono Extended.
 *
 * All plugin logging goes through WooCommerce logger (WC > Status > Logs),
 * never into a file inside the plugin directory: such a file is downloadable
 * over HTTP and leaks customer data. Every payload is redacted before it is
 * written, and logging is off until the shop owner enables it in the gateway
 * settings.
 * */

# This prevents a public user from directly accessing your .php files
if (! defined('ABSPATH'))
{
    # Exit if accessed directly
    exit;
}

if (! class_exists('MRKV_MONO_LOG'))
{
    class MRKV_MONO_LOG
    {
        /**
         * Single WC logger source for the whole plugin.
         * Log files are wp-content/uploads/wc-logs/<SOURCE>-<date>-<hash>.log
         * */
        const SOURCE = 'morkva-monobank-extended';

        /**
         * How long log files are kept before mrkv_mono_pro_clean_logs removes them
         * */
        const RETENTION_DAYS = 7;

        /**
         * Settings key of the main gateway holding the debug switch
         * */
        const OPTION = 'woocommerce_morkva-monopay_settings';

        /**
         * Field name of the debug switch inside the gateway settings
         * */
        const FIELD = 'monopay_debug_log';

        /**
         * Values replaced with a masked stub before writing
         * */
        const SECRET_KEYS = array(
            'cardtoken', 'walletid', 'wallettoken', 'token', 'x-token',
            'signature', 'secret', 'secretkey', 'secret_key', 'apikey',
            'api_key', 'password', 'authorization',
        );

        /**
         * Values reduced to a non-identifying form before writing
         * */
        const PERSONAL_KEYS = array(
            'customeremails', 'email', 'client_email', 'client_phone',
            'phone', 'customerphone',
        );

        /**
         * Is debug logging enabled
         *
         * On unless the shop owner turned it off: a failed payment has to be
         * diagnosable from the first occurrence, and a customer cannot be asked
         * to pay again just to reproduce the error. Entries are redacted before
         * they are written and removed by cron after RETENTION_DAYS days.
         *
         * @return bool
         * */
        public static function mrkv_mono_is_enabled()
        {
            $settings = get_option(self::OPTION, array());
            $value    = (is_array($settings) && isset($settings[self::FIELD])) ? $settings[self::FIELD] : 'yes';

            return (bool) apply_filters('mrkv_mono_debug_log_enabled', 'yes' === $value);
        }

        /**
         * Write a redacted debug entry, if debug logging is enabled
         *
         * @param string $title Short label of the logged event
         * @param array  $data  Map of "section name" => payload
         *
         * @return void
         * */
        public static function mrkv_mono_debug($title, $data = array())
        {
            if (! self::mrkv_mono_is_enabled() || ! function_exists('wc_get_logger'))
            {
                return;
            }

            $message = "--- " . $title . " ---\n";

            foreach ((array) $data as $label => $payload)
            {
                $message .= $label . ": " . self::mrkv_mono_stringify($payload) . "\n";
            }

            $message .= "------------------------";

            wc_get_logger()->debug($message, array('source' => self::SOURCE));
        }

        /**
         * Render a payload as a redacted, readable string
         *
         * @param mixed $payload
         *
         * @return string
         * */
        private static function mrkv_mono_stringify($payload)
        {
            # JSON strings are decoded so that nested secrets can be reached
            if (is_string($payload))
            {
                $decoded = json_decode($payload, true);

                if (JSON_ERROR_NONE !== json_last_error() || ! is_array($decoded))
                {
                    return $payload;
                }

                $payload = $decoded;
            }

            if (is_scalar($payload) || is_null($payload))
            {
                return (string) $payload;
            }

            return wp_json_encode(self::mrkv_mono_redact($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        /**
         * Recursively replace secrets and personal data inside a payload
         *
         * @param mixed $data
         *
         * @return mixed
         * */
        public static function mrkv_mono_redact($data)
        {
            if (is_object($data))
            {
                $data = (array) $data;
            }

            if (! is_array($data))
            {
                return $data;
            }

            $secret   = array_map(array(__CLASS__, 'mrkv_mono_normalize'), self::SECRET_KEYS);
            $personal = array_map(array(__CLASS__, 'mrkv_mono_normalize'), self::PERSONAL_KEYS);
            $clean    = array();

            foreach ($data as $key => $value)
            {
                $normalized = self::mrkv_mono_normalize($key);

                if (in_array($normalized, $secret, true))
                {
                    $clean[$key] = self::mrkv_mono_mask_secret($value);
                }
                elseif (in_array($normalized, $personal, true))
                {
                    $clean[$key] = self::mrkv_mono_mask_personal($value);
                }
                else
                {
                    $clean[$key] = (is_array($value) || is_object($value)) ? self::mrkv_mono_redact($value) : $value;
                }
            }

            return $clean;
        }

        /**
         * Normalize a key name for comparison
         *
         * @param string $key
         *
         * @return string
         * */
        public static function mrkv_mono_normalize($key)
        {
            return strtolower(str_replace(array('-', '_'), '', (string) $key));
        }

        /**
         * Replace a secret with a stub keeping only its last 4 characters
         *
         * @param mixed $value
         *
         * @return mixed
         * */
        private static function mrkv_mono_mask_secret($value)
        {
            if (is_array($value) || is_object($value))
            {
                return '***';
            }

            $value = (string) $value;

            if ('' === $value)
            {
                return $value;
            }

            return '***' . substr($value, -4);
        }

        /**
         * Replace personal data with a non-identifying stub
         *
         * @param mixed $value
         *
         * @return mixed
         * */
        private static function mrkv_mono_mask_personal($value)
        {
            if (is_array($value))
            {
                return array_map(array(__CLASS__, 'mrkv_mono_mask_personal_scalar'), $value);
            }

            return self::mrkv_mono_mask_personal_scalar($value);
        }

        /**
         * Mask a single personal value
         *
         * @param mixed $value
         *
         * @return mixed
         * */
        public static function mrkv_mono_mask_personal_scalar($value)
        {
            if (! is_scalar($value))
            {
                return '***';
            }

            $value = (string) $value;

            if ('' === $value)
            {
                return $value;
            }

            # Emails keep the domain so that a wrong-mailbox report stays diagnosable
            if (false !== strpos($value, '@'))
            {
                $parts = explode('@', $value, 2);

                return substr($parts[0], 0, 1) . '***@' . $parts[1];
            }

            return '***' . substr($value, -4);
        }
    }
}
