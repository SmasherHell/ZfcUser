<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of MailerInterface
 *
 * @author Bouchez Guillaume <guillaume.bouchez@infolegale.fr>
 */

namespace ZfcUser\Service;

use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail;


interface MailerInterface
{
    /**
     * Set Mail Message Container
     * @param Zend\Mail\Message
     * @return MailerInterface
     */
    public function setMessage(Message $message);
    
    /**
     * Set Mail Transport Container
     * @param Zend\Mail\Transport\Sendmail $transport
     * @return MailerInterface
     */
    public function setTransport(Sendmail $transport);
    
    /**
     * @param string $sender;
     * @return MailerInterface
     */
    public function setFrom($sender);
    
    /**
     * @param string $subject
     * @return MailerInterface
     */
    public function setSubject($subject);
    
    /**
     * @param string $body
     * @return MailerInterface
    */
    public function setBody($body);
    
    /**
     * @param string $email
     * @param string @displayName
     * @return MailerInterface
     */
    public function addTo($email, $displayName = null);
    
    /**
     * @return Zend\Mail\Message
     */
    public function getMessage();
    
    /**
     * @return Zend\Mail\Transport\Sendmail
     */
    public function getTransport();
    /**
     * Send mail
     */
    public function send();
}
