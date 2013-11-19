<?php

namespace ZfcUser\Form;

use ZfcBase\InputFilter\ProvidesEventsInputFilter;
use ZfcUser\Options\AuthenticationOptionsInterface;

class LoginFilter extends ProvidesEventsInputFilter
{
    public function __construct(AuthenticationOptionsInterface $options)
    {
        $identityParams = array(
            'name'       => 'identity',
            'required'   => true,
            'validators' => array()
        );

        $identityFields = $options->getAuthIdentityFields();
        if ($identityFields == array('email')) {
            $validators = array('name' => 'EmailAddress');
            array_push($identityParams['validators'], $validators);
        }

        $this->add($identityParams);

        $this->getEventManager()->trigger('init', $this);
    }
}
