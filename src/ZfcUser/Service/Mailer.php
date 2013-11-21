<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Mailer
 *
 * @author Bouchez Guillaume <guillaume.bouchez@infolegale.fr>
 */

namespace ZfcUser\Service;

use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\ServiceManagerAwareInterface;

class Mailer implements ServiceManagerAwareInterface, MailerInterface
{
    /**
     * @var ServiceManager
     */
    protected $_serviceManager;
    
    /**
     * @var Message 
     */
    protected $_message;
    
    /**
     * @var Sendmail 
     */
    protected $_transport;
    
    public function __construct()
    {
        
    }
    
    public function addTo($email, $displayName = null)
    {
        $this->_message->addTo($email, $displayName);
        return $this;
    }

    public function getMessage()
    {
        return $this->_message;
    }

    public function getTransport()
    {
        return $this->_transport;
    }

    public function send()
    {
        return $this->_transport->send($this->_message);
    }

    public function setBody($body)
    {
        $this->_message->setBody($body);
        return $this;
    }

    public function setFrom($sender)
    {
        $this->_message->setFrom($sender);
        return $this;
    }

    public function setMessage(Message $message)
    {
        $this->_message = $message;
        return $this;
    }

    public function setSubject($subject)
    {
        $this->_message->setSubject($subject);
        return $this;
    }

    public function setTransport(Sendmail $transport)
    {
        $this->_transport = $transport;
        return $this;
    }

    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->_serviceManager = $serviceManager;
        return $this;
    }

}
