<?php

namespace ZfcUser\Options;

interface AuthenticationOptionsInterface extends PasswordOptionsInterface
{

    /**
     * set login form timeout in seconds
     *
     * @param int $loginFormTimeout
     */
    public function setLoginFormTimeout($loginFormTimeout);

    /**
     * set login form timeout in seconds
     *
     * @param int $loginFormTimeout
     */
    public function getLoginFormTimeout();

    /**
     * set auth identity fields
     *
     * @param array $authIdentityFields
     * @return ModuleOptions
     */
    public function setAuthIdentityFields($authIdentityFields);

    /**
     * get auth identity fields
     *
     * @return array
     */
    public function getAuthIdentityFields();
    
    /**
     * Get reset password mail sender 
     * @return type
     */
    public function getResetPasswordMailSender();

    /**
     * Get authentification token timeout
     * @return type
     */
    public function getAuthentificationTokenTimeout();

    /**
     * Set reset password mail sender
     * @param type $resetPasswordMailSender
     * @return \ZfcUser\Options\ModuleOptions
     */
    public function setResetPasswordMailSender($resetPasswordMailSender);

    /**
     * Set authentification token timeout
     * @param type $authentificationTokenTimeout
     * @return \ZfcUser\Options\ModuleOptions
     */
    public function setAuthentificationTokenTimeout($authentificationTokenTimeout);

}
