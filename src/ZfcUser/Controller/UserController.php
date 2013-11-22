<?php

namespace ZfcUser\Controller;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Stdlib\Parameters;
use Zend\Stdlib\ResponseInterface as Response;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\PhpRenderer;
use ZfcUser\Options\UserControllerOptionsInterface;
use ZfcUser\Service\MailerInterface;
use ZfcUser\Service\User as UserService;

class UserController extends AbstractActionController
{
    const ROUTE_FORGOTPASSWD = 'zfcuser/forgotpassword';
    const ROUTE_CHANGEPASSWD = 'zfcuser/changepassword';
    const ROUTE_LOGIN        = 'zfcuser/login';
    const ROUTE_REGISTER     = 'zfcuser/register';
    const ROUTE_CHANGEEMAIL  = 'zfcuser/changeemail';

    const CONTROLLER_NAME    = 'zfcuser';

    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var Form
     */
    protected $loginForm;

    /**
     * @var Form
     */
    protected $registerForm;

    /**
     * @var Form
     */
    protected $changePasswordForm;

    /**
     * @var Form
     */
    protected $changeEmailForm;
    
    /**
     *
     * @var Form
     */
    protected $forgotPasswordForm;

    /**
     * @todo Make this dynamic / translation-friendly
     * @var string
     */
    protected $failedLoginMessage = 'Authentication failed. Please try again.';

    /**
     * @var UserControllerOptionsInterface
     */
    protected $options;
    
    /**
     * @var MailerInterface
     */
    protected $mailerService;

    /**
     * User page
     */
    public function indexAction()
    {
        if (!$this->zfcUserAuthentication()->hasIdentity()) {
            return $this->redirect()->toRoute(static::ROUTE_LOGIN);
        }
        return new ViewModel();
    }

    /**
     * Login form
     */
    public function loginAction()
    {
        if ($this->zfcUserAuthentication()->getAuthService()->hasIdentity()) {
            return $this->redirect()->toRoute($this->getOptions()->getLoginRedirectRoute());
        }

        $request = $this->getRequest();
        $form    = $this->getLoginForm();

        if ($this->getOptions()->getUseRedirectParameterIfPresent() && $request->getQuery()->get('redirect')) {
            $redirect = $request->getQuery()->get('redirect');
        } else {
            $redirect = false;
        }

        if (!$request->isPost()) {
            return array(
                'loginForm' => $form,
                'redirect'  => $redirect,
                'enableRegistration' => $this->getOptions()->getEnableRegistration(),
            );
        }

        $form->setData($request->getPost());

        if (!$form->isValid()) {
            $this->flashMessenger()->setNamespace('zfcuser-login-form')->addMessage($this->failedLoginMessage);
            return $this->redirect()->toUrl($this->url()->fromRoute(static::ROUTE_LOGIN).($redirect ? '?redirect='.$redirect : ''));
        }

        // clear adapters
        $this->zfcUserAuthentication()->getAuthAdapter()->resetAdapters();
        $this->zfcUserAuthentication()->getAuthService()->clearIdentity();

        return $this->forward()->dispatch(static::CONTROLLER_NAME, array('action' => 'authenticate'));
    }

    /**
     * Logout and clear the identity
     */
    public function logoutAction()
    {
        $this->zfcUserAuthentication()->getAuthAdapter()->resetAdapters();
        $this->zfcUserAuthentication()->getAuthAdapter()->logoutAdapters();
        $this->zfcUserAuthentication()->getAuthService()->clearIdentity();

        $redirect = $this->params()->fromPost('redirect', $this->params()->fromQuery('redirect', false));

        if ($this->getOptions()->getUseRedirectParameterIfPresent() && $redirect) {
            return $this->redirect()->toUrl($redirect);
        }

        return $this->redirect()->toRoute($this->getOptions()->getLogoutRedirectRoute());
    }

    /**
     * General-purpose authentication action
     */
    public function authenticateAction()
    {
        if ($this->zfcUserAuthentication()->getAuthService()->hasIdentity()) {
            return $this->redirect()->toRoute($this->getOptions()->getLoginRedirectRoute());
        }

        $adapter = $this->zfcUserAuthentication()->getAuthAdapter();
        $redirect = $this->params()->fromPost('redirect', $this->params()->fromQuery('redirect', false));

        $result = $adapter->prepareForAuthentication($this->getRequest());
        // Return early if an adapter returned a response
        if ($result instanceof Response) {
            return $result;
        }
        
        $auth = $this->zfcUserAuthentication()->getAuthService()->authenticate($adapter);
        
        // var_dump($result, $auth);exit;

        if (!$auth->isValid()) {
            $this->flashMessenger()->setNamespace('zfcuser-login-form')->addMessage($this->failedLoginMessage);
            $adapter->resetAdapters();
            return $this->redirect()->toUrl($this->url()->fromRoute(static::ROUTE_LOGIN)
                . ($redirect ? '?redirect='.$redirect : ''));
        }

        if ($this->getOptions()->getUseRedirectParameterIfPresent() && $redirect) {
            return $this->redirect()->toUrl($redirect);
        }

        $route = $this->getOptions()->getLoginRedirectRoute();

        if (is_callable($route)) {
            $route = $route($this->zfcUserAuthentication()->getIdentity());
        }

        return $this->redirect()->toRoute($route);
    }

    /**
     * Register new user
     */
    public function registerAction()
    {
        // if the user is logged in, we don't need to register
        if ($this->zfcUserAuthentication()->hasIdentity()) {
            // redirect to the login redirect route
            return $this->redirect()->toRoute($this->getOptions()->getLoginRedirectRoute());
        }
        // if registration is disabled
        if (!$this->getOptions()->getEnableRegistration()) {
            return array('enableRegistration' => false);
        }
        
        $request = $this->getRequest();
        $service = $this->getUserService();
        $form = $this->getRegisterForm();

        if ($this->getOptions()->getUseRedirectParameterIfPresent() && $request->getQuery()->get('redirect')) {
            $redirect = $request->getQuery()->get('redirect');
        } else {
            $redirect = false;
        }

        $redirectUrl = $this->url()->fromRoute(static::ROUTE_REGISTER)
            . ($redirect ? '?redirect=' . $redirect : '');
        $prg = $this->prg($redirectUrl, true);

        if ($prg instanceof Response) {
            return $prg;
        } elseif ($prg === false) {
            return array(
                'registerForm' => $form,
                'enableRegistration' => $this->getOptions()->getEnableRegistration(),
                'redirect' => $redirect,
            );
        }

        $post = $prg;
        $user = $service->register($post);

        $redirect = isset($prg['redirect']) ? $prg['redirect'] : null;

        if (!$user) {
            return array(
                'registerForm' => $form,
                'enableRegistration' => $this->getOptions()->getEnableRegistration(),
                'redirect' => $redirect,
            );
        }

        if ($service->getOptions()->getLoginAfterRegistration()) {
            $identityFields = $service->getOptions()->getAuthIdentityFields();
            if (in_array('email', $identityFields)) {
                $post['identity'] = $user->getEmail();
            } elseif (in_array('username', $identityFields)) {
                $post['identity'] = $user->getUsername();
            }
            $post['credential'] = $post['password'];
            $request->setPost(new Parameters($post));
            return $this->forward()->dispatch(static::CONTROLLER_NAME, array('action' => 'authenticate'));
        }

        // TODO: Add the redirect parameter here...
        return $this->redirect()->toUrl($this->url()->fromRoute(static::ROUTE_LOGIN) . ($redirect ? '?redirect='.$redirect : ''));
    }

    /**
     * Change the users password
     */
    public function changepasswordAction()
    {
        // Force login with token
        $token      = $this->params()->fromRoute('token');
        $tokenMode  = !empty($token);
        
        if ($this->params()->fromRoute('token')) {
            $fulltoken = base64_decode($this->params()->fromRoute('token'));
            $tokenSplit = explode("|", $fulltoken);
            $check = $this->getUserService()->checkAuthToken($tokenSplit[0], $tokenSplit[1]);
            $this->getRequest()->setPost(new Parameters(array(
                'identity'  => $tokenSplit[1],
                'token'     => $tokenSplit[0],
            )));
            $this->forward()->dispatch(static::CONTROLLER_NAME, array('action' => 'authenticate'));
            // Reset redirect
            if ($this->getResponse()->getStatusCode() == 302) {
                $this->getResponse()->setStatusCode(200);
            }
        }
        
        // if the user isn't logged in, we can't change password
        if (!$this->zfcUserAuthentication()->hasIdentity() || !$check) {
            // redirect to the login redirect route
            return $this->redirect()->toRoute($this->getOptions()->getLoginRedirectRoute());
        }

        $form = $this->getChangePasswordForm();
        $prg = $this->prg(static::ROUTE_CHANGEPASSWD);
        if ($tokenMode) {
            $form->remove('credential');
        }
        $fm = $this->flashMessenger()->setNamespace('change-password')->getMessages();
        if (isset($fm[0])) {
            $status = $fm[0];
        } else {
            $status = null;
        }

        if ($prg instanceof Response) {
            return $prg;
        } elseif ($prg === false) {
            return array(
                'status' => $status,
                'tokenMode' => $tokenMode,
                'changePasswordForm' => $form,
            );
        }

        $form->setData($prg);

        if (!$form->isValid()) {
            return array(
                'status' => false,
                'tokenMode' => $tokenMode,
                'changePasswordForm' => $form,
            );
        }

        if (!$this->getUserService()->changePassword($form->getData(), $this->params()->fromRoute('token'))) {
            return array(
                'status' => false,
                'tokenMode' => $tokenMode,
                 'changePasswordForm' => $form,
            );
        }
        if ($tokenMode) {
            // Clear identity
            $this->zfcUserAuthentication()->getAuthAdapter()->resetAdapters();
            $this->zfcUserAuthentication()->getAuthService()->clearIdentity();
            // Redirect
            $this->flashMessenger()->setNamespace('change-password')->addMessage(true);
            return $this->redirect()->toRoute(static::ROUTE_LOGIN);
        }
        $this->flashMessenger()->setNamespace('change-password')->addMessage(true);
        return $this->redirect()->toRoute(static::ROUTE_CHANGEPASSWD);
    }

    public function changeEmailAction()
    {
        // if the user isn't logged in, we can't change email
        if (!$this->zfcUserAuthentication()->hasIdentity()) {
            // redirect to the login redirect route
            return $this->redirect()->toRoute($this->getOptions()->getLoginRedirectRoute());
        }

        $form = $this->getChangeEmailForm();
        $request = $this->getRequest();
        $request->getPost()->set('identity', $this->getUserService()->getAuthService()->getIdentity()->getEmail());

        $fm = $this->flashMessenger()->setNamespace('change-email')->getMessages();
        if (isset($fm[0])) {
            $status = $fm[0];
        } else {
            $status = null;
        }

        $prg = $this->prg(static::ROUTE_CHANGEEMAIL);
        if ($prg instanceof Response) {
            return $prg;
        } elseif ($prg === false) {
            return array(
                'status' => $status,
                'changeEmailForm' => $form,
            );
        }

        $form->setData($prg);

        if (!$form->isValid()) {
            return array(
                'status' => false,
                'changeEmailForm' => $form,
            );
        }

        $change = $this->getUserService()->changeEmail($prg);

        if (!$change) {
            $this->flashMessenger()->setNamespace('change-email')->addMessage(false);
            return array(
                'status' => false,
                'changeEmailForm' => $form,
            );
        }

        $this->flashMessenger()->setNamespace('change-email')->addMessage(true);
        return $this->redirect()->toRoute(static::ROUTE_CHANGEEMAIL);
    }
    
    public function forgotPasswordAction()
    {
        if ($this->zfcUserAuthentication()->hasIdentity()) {
            $this->redirect()->toRoute($this->getOptions()->getLoginRedirectRoute());
        }
        $form   = $this->getForgotPasswordForm();
        $status = false;
        $prg    = $this->prg(static::ROUTE_FORGOTPASSWD);
                
        if ($prg instanceof Response) {
            return $prg;
        }elseif ($prg === false && !$this->getRequest()->isPost()) {
            return array(
                'forgotPasswordForm' => $form,
                'status'             => false
            );
        }
        // Get identity from Mail
        $service = $this->getUserService();
        // var_dump($prg);
        $user = $service->getUserMapper()
                        ->findByEmail($prg['identity']);
        // Send Mail with token
        if($user){
            $token      = $this->getUserService()->generateAuthToken($user);  
            $sender     = $this->getOptions()->getResetPasswordMailSender();
            $translator = $this->getServiceLocator()->get('translator');
            $subject    = $translator->translate('Reset Password request');
            $renderer   = new PhpRenderer();
            $resolver   = new \Zend\View\Resolver\TemplateMapResolver(array(
                'zfcuser_forgot_password_mail'  => __DIR__ . '/../../../view/zfc-user/user/forgot-password-mail-template.phtml',
            ));
            $renderer->setHelperPluginManager($this->getServiceLocator()->get('ViewRenderer')->getHelperPluginManager());
            $renderer->setResolver($resolver);
            $view       = new ViewModel();
            
            $view->setTemplate('zfcuser_forgot_password_mail');
            $view->setVariables(array(
                'token' => $token . "|" . $user->getemail(),
            ));
            $messagePart= new \Zend\Mime\Part($renderer->render($view));
            $messagePart->type = "text/html";
            $message    = new \Zend\Mime\Message();
            $message->setParts(array($messagePart));
            $mailer     = $this->getMailerService();
            $mailer->setFrom($sender)
                    ->addTo($user->getEmail(), $user->getDisplayName())
                    ->setSubject($subject)
                    ->setBody($message)
                    ->send();
            $status = true;
        }
        return array(
            'forgotPasswordForm' => $form,
            'status'             => $status,
        );
    }
    
    public function cancelTokenAction()
    {
        $fulltoken = base64_decode($this->params()->fromRoute('token'));
        $tokenSplit = explode("|", $fulltoken);
        $user = $this->getUserService()->getUserMapper()->findByTokenAndEmail($tokenSplit[0], $tokenSplit[1]);
        $status = false;
        if($user) {
            $status = $this->getUserService()->deleteAuthToken($user);
        }
        
        return new ViewModel(array(
            'status' => $status,
        ));
    }

    /**
     * Getters/setters for DI stuff
     */

    public function getUserService()
    {
        if (!$this->userService) {
            $this->userService = $this->getServiceLocator()->get('zfcuser_user_service');
        }
        return $this->userService;
    }

    public function setUserService(UserService $userService)
    {
        $this->userService = $userService;
        return $this;
    }
    
    public function getMailerService()
    {
        if (!$this->mailerService) {
            $this->setMailerService($this->getServiceLocator()->get('zfcuser_mailer'));
        }
        
        return $this->mailerService;
    }
    
    public function setMailerService(MailerInterface $mailerService)
    {
        $this->mailerService = $mailerService;
    }

    public function getRegisterForm()
    {
        if (!$this->registerForm) {
            $this->setRegisterForm($this->getServiceLocator()->get('zfcuser_register_form'));
        }
        return $this->registerForm;
    }

    public function setRegisterForm(Form $registerForm)
    {
        $this->registerForm = $registerForm;
    }

    public function getLoginForm()
    {
        if (!$this->loginForm) {
            $this->setLoginForm($this->getServiceLocator()->get('zfcuser_login_form'));
        }
        return $this->loginForm;
    }

    public function setLoginForm(Form $loginForm)
    {
        $this->loginForm = $loginForm;
        $fm = $this->flashMessenger()->setNamespace('zfcuser-login-form')->getMessages();
        if (isset($fm[0])) {
            $this->loginForm->setMessages(
                array('identity' => array($fm[0]))
            );
        }
        return $this;
    }

    public function getChangePasswordForm()
    {
        if (!$this->changePasswordForm) {
            $this->setChangePasswordForm($this->getServiceLocator()->get('zfcuser_change_password_form'));
        }
        return $this->changePasswordForm;
    }

    public function setChangePasswordForm(Form $changePasswordForm)
    {
        $this->changePasswordForm = $changePasswordForm;
        return $this;
    }

    /**
     * set options
     *
     * @param UserControllerOptionsInterface $options
     * @return UserController
     */
    public function setOptions(UserControllerOptionsInterface $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * get options
     *
     * @return UserControllerOptionsInterface
     */
    public function getOptions()
    {
        if (!$this->options instanceof UserControllerOptionsInterface) {
            $this->setOptions($this->getServiceLocator()->get('zfcuser_module_options'));
        }
        return $this->options;
    }

    /**
     * Get changeEmailForm.
     *
     * @return changeEmailForm.
     */
    public function getChangeEmailForm()
    {
        if (!$this->changeEmailForm) {
            $this->setChangeEmailForm($this->getServiceLocator()->get('zfcuser_change_email_form'));
        }
        return $this->changeEmailForm;
    }

    /**
     * Set changeEmailForm.
     *
     * @param changeEmailForm the value to set.
     */
    public function setChangeEmailForm($changeEmailForm)
    {
        $this->changeEmailForm = $changeEmailForm;
        return $this;
    }
    
    public function setForgotPasswordForm($forgotPasswordForm)
    {
        $this->forgotPasswordForm = $forgotPasswordForm;
        return $this;
    }
    
    public function getForgotPasswordForm()
    {
        if (!isset($this->forgotPasswordForm)) {
            $this->setForgotPasswordForm($this->getServiceLocator()->get('zfcuser_forgot_password'));
        }
        return $this->forgotPasswordForm;
    }
}
