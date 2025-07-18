<?php

namespace LibreNMS\Authentication;

use App\Facades\LibrenmsConfig;
use LDAP\Connection;
use LibreNMS\Enum\LegacyAuthLevel;
use LibreNMS\Exceptions\AuthenticationException;
use LibreNMS\Exceptions\LdapMissingException;

class ADAuthorizationAuthorizer extends MysqlAuthorizer
{
    use LdapSessionCache;
    use ActiveDirectoryCommon;

    protected static $AUTH_IS_EXTERNAL = true;
    protected static $CAN_UPDATE_PASSWORDS = false;

    protected ?Connection $ldap_connection = null;

    public function __construct()
    {
        if (! function_exists('ldap_connect')) {
            throw new LdapMissingException();
        }

        // Disable certificate checking before connect if required
        if (LibrenmsConfig::has('auth_ad_check_certificates') &&
            LibrenmsConfig::get('auth_ad_check_certificates') == 0) {
            putenv('LDAPTLS_REQCERT=never');
        }

        // Set up connection to LDAP server
        $this->ldap_connection = ldap_connect(LibrenmsConfig::get('auth_ad_url'));
        if (empty($this->ldap_connection)) {
            throw new AuthenticationException('Fatal error while connecting to AD, uri not valid: ' . LibrenmsConfig::get('auth_ad_url'));
        }

        // disable referrals and force ldap version to 3
        ldap_set_option($this->ldap_connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($this->ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3);

        // Bind to AD
        if (LibrenmsConfig::has('auth_ad_binduser') && LibrenmsConfig::has('auth_ad_bindpassword')) {
            // With specified bind user
            if (! ldap_bind($this->ldap_connection, LibrenmsConfig::get('auth_ad_binduser') . '@' . LibrenmsConfig::get('auth_ad_domain'), LibrenmsConfig::get('auth_ad_bindpassword'))) {
                echo ldap_error($this->ldap_connection);
            }
        } else {
            // Anonymous
            if (! ldap_bind($this->ldap_connection)) {
                echo ldap_error($this->ldap_connection);
            }
        }
    }

    public function authenticate($credentials)
    {
        if (isset($credentials['username']) && $this->userExists($credentials['username'])) {
            return true;
        }

        if (LibrenmsConfig::get('http_auth_guest')) {
            return true;
        }

        throw new AuthenticationException();
    }

    public function userExists($username, $throw_exception = false)
    {
        if ($this->authLdapSessionCacheGet('user_exists')) {
            return true;
        }

        $search = ldap_search(
            $this->ldap_connection,
            LibrenmsConfig::get('auth_ad_base_dn'),
            $this->userFilter($username),
            ['samaccountname']
        );
        if ($search === false) {
            throw new AuthenticationException('User search failed: ' . ldap_error($this->ldap_connection));
        }
        $entries = ldap_get_entries($this->ldap_connection, $search);

        if ($entries['count']) {
            /*
             * Cache positiv result as this will result in more queries which we
             * want to speed up.
             */
            $this->authLdapSessionCacheSet('user_exists', 1);

            return true;
        }

        return false;
    }

    public function getRoles(string $username): array|false
    {
        $roles = $this->authLdapSessionCacheGet('roles');
        if ($roles !== null) {
            return $roles;
        }
        $roles = [];

        // Find all defined groups $username is in
        $search = ldap_search(
            $this->ldap_connection,
            LibrenmsConfig::get('auth_ad_base_dn'),
            $this->userFilter($username),
            ['memberOf']
        );
        $entries = ldap_get_entries($this->ldap_connection, $search);

        // collect all roles
        $auth_ad_groups = LibrenmsConfig::get('auth_ad_groups');
        foreach ($entries[0]['memberof'] as $index => $entry) {
            if ($index == 'count') {
                continue; // skip count entry
            }

            $group_cn = $this->getCn($entry);

            if (isset($auth_ad_groups[$group_cn]['roles']) && is_array($auth_ad_groups[$group_cn]['roles'])) {
                $roles = array_merge($roles, $auth_ad_groups[$group_cn]['roles']);
            } elseif (isset($auth_ad_groups[$group_cn]['level'])) {
                $role = LegacyAuthLevel::tryFrom($auth_ad_groups[$group_cn]['level'])?->getName();
                if ($role) {
                    $roles[] = $role;
                }
            }
        }

        $roles = array_unique($roles);
        $this->authLdapSessionCacheSet('roles', $roles);

        return $roles;
    }

    public function getUserid($username)
    {
        $user_id = $this->authLdapSessionCacheGet('userid');
        if (isset($user_id)) {
            return $user_id;
        } else {
            $user_id = -1;
        }

        $attributes = ['objectsid'];
        $search = ldap_search(
            $this->ldap_connection,
            LibrenmsConfig::get('auth_ad_base_dn'),
            $this->userFilter($username),
            $attributes
        );
        if ($search === false) {
            throw new AuthenticationException('Role search failed: ' . ldap_error($this->ldap_connection));
        }
        $entries = ldap_get_entries($this->ldap_connection, $search);

        if ($entries['count']) {
            $user_id = $this->getUseridFromSid($this->sidFromLdap($entries[0]['objectsid'][0]));
        }

        $this->authLdapSessionCacheSet('userid', $user_id);

        return $user_id;
    }

    protected function getConnection(): ?Connection
    {
        return $this->ldap_connection;
    }
}
