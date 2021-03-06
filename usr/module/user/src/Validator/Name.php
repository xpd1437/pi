<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt BSD 3-Clause License
 */


namespace Module\User\Validator;

use Pi;
use Zend\Validator\AbstractValidator;

/**
 * Validator for username
 *
 * @author Liu Chuang <liuchuang@eefocus.com>
 */
class Name extends AbstractValidator
{
    const INVALID   = 'nameInvalid';
    const RESERVED  = 'nameReserved';
    const TOO_SHORT = 'stringLengthTooShort';
    const TOO_LONG  = 'stringLengthTooLong';

    protected $messageTemplates;
    protected $formatMessage;

    protected $messageVariables = array(
        'formatHint' => 'formatHint',
        'max'        => 'max',
        'min'        => 'min',
    );

    protected $formatHint;
    protected $max;
    protected $min;

    public function __construct()
    {
        $this->messageTemplates = array(
            self::INVALID   => __('Invalid user name: %formatHint%'),
            self::RESERVED  => __('User name is reserved'),
            self::TOO_SHORT => __('User name is less than %min% characters long'),
            self::TOO_LONG  => __('User name is more than %max% characters long'),
        );

        $this->formatMessage = array(
            'strict'    => __('Only alphabetic and digits are allowed with leading alphabetic'),
            'medium'    => __('Only ASCII characters are allowed'),
            'loose'     => __('Multibyte characters are allowed'),
        );

        parent::__construct();
    }

    protected $formatPattern = array(
        'strict'    => '/^[^a-zA-Z]|[^a-zA-Z0-9]/',
        'medium'    => '/[^a-zA-Z0-9\_\-\<\>\,\.\$\%\#\@\!\\\'\"]/',
        'loose'     => '/[\000-\040]/',
    );

    protected $options = array(
        'format'            => 'loose',
        'backlist'          => array(),
        'checkDuplication'  => true,
    );

    /**
     * Name validate
     *
     * @param  mixed $value
     * @param  array $context
     * @return boolean
     */
    public function isValid($value, $context = null)
    {
        $this->setValue($value);
        $this->setConfigOption();

        $format = empty($this->options['format']) ? 'loose' : $this->options['format'];
        if (preg_match($this->formatPattern[$format], $value)) {
            $this->formatHint = $this->formatMessage[$format];
            $this->error(static::INVALID);
            return false;
        }
        if (!empty($this->options['backlist'])) {
            $pattern = is_array($this->options['backlist']) ? implode('|', $this->options['backlist']) : $this->options['backlist'];
            if (preg_match('/(' . $pattern . ')/', $value)) {
                $this->error(static::RESERVED);
                return false;
            }
        }
        if ($this->options['max']) {
            if ($this->options['max'] < strlen($value)) {
                $this->max = $this->options['max'];
                $this->error(static::TOO_LONG);
                return false;
            }
        }
        if ($this->options['min']) {
            if ($this->options['min'] > strlen($value)) {
                $this->min = $this->options['min'];
                $this->error(static::TOO_SHORT);
                return false;
            }
        }
        if ($this->options['checkDuplication']) {
            $where = array('name' => $value);
            if (!empty($context['uid'])) {
                $where['id <> ?'] = $context['uid'];
            }
            //$rowset = Pi::model('account', 'user')->select($where);
            $count = Pi::model('account', 'user')->count($where);
            if ($count) {
                $this->error(static::RESERVED);
                return false;
            }
        }

        return true;
    }

    /**
     * Set display validator according to config
     *
     * @return $this
     */
    public function setConfigOption()
    {
        $this->options = array(
            'min'               => Pi::user()->config('name_min'),
            'max'               => Pi::user()->config('name_max'),
            'format'            => Pi::user()->config('name_format'),
            'backlist'          => Pi::user()->config('name_backlist'),
            'checkDuplication'  => true,
        );

        return $this;
    }

}