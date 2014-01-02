<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

namespace Pi\Application\Installer;

use Pi;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\Event;

/**
 * Module installer resource handler
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Resource implements ListenerAggregateInterface
{
    /** @var ListenerAggregateInterface Listener container */
    protected $listener;

    /** @var Event Installer event */
    protected $event;

    /**
     * Constructor
     *
     * @param Event $event
     */
    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * Attach listeners
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listener = $events->attach(
            'process',
            array($this, 'processResources')
        );
    }

    /**
     * Detach listeners
     *
     * @param EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        $events->detach($this->listener);
    }

    /**
     * Process resources
     *
     * Generates results for each resource in an associative array:
     *
     * <code>
     *  array(
     *      '<resource-name>' => array(
     *          'status'    => <true|false>,
     *          'message'   => <Message array generated by action>[],
     *      ),
     *  );
     * </code>
     *
     * @param Event $e
     * @return void
     */
    public function processResources(Event $e)
    {
        $this->event = $e;
        $result = $this->event->getParam('result');
        $resourceList = $this->resourceList();
        foreach ($resourceList as $resource) {
            $ret = $this->loadResource($resource);
            if (null === $ret) {
                continue;
            }
            $result['resource-' . $resource] = $ret;
            if (false === $ret['status']) {
                break;
            }
            if (Pi::service()->hasService('log')) {
                Pi::service('log')->info(
                    sprintf('Module resource %s is loaded.', $resource)
                );
            }
        }
        $this->event->setParam('result', $result);

        return;
    }

    /**
     * Get available resource list
     *
     * @return array
     */
    protected function resourceList()
    {
        $resourceList = array();
        $iterator = new \DirectoryIterator(__DIR__ . '/Resource');
        foreach ($iterator as $fileinfo) {
            if (!$fileinfo->isFile()) {
                continue;
            }
            $fileName = $fileinfo->getFilename();
            if (!preg_match('/^([^\.]+)\.php$/', $fileName, $matches)) {
                continue;
            }
            $resource = $matches[1];
            if ($resource == 'Config' || $resource == 'AbstractResource') {
                continue;
            }
            $resourceName = strtolower(implode(
                '_',
                array_filter(preg_split('/(?=[A-Z])/', $resource))
            ));
            $resourceList[] = $resourceName;
        }
        $resourceList[] = 'config';

        $config = $this->event->getParam('config');
        if (!empty($config['resource'])) {
            $resources = array_keys($config['resource']);
            $resourceList = array_unique(
                array_merge($resources, $resourceList)
            );
        }

        return $resourceList;
    }

    /**
     * Load and perform resource actions
     *
     * Returns result of the resource, null for failure but ignored, or array:
     *
     * <code>
     *  array(
     *      'status'    => <true|false>,
     *      'message'   => <Message array>[],
     *  );
     * </code>
     *
     * @param string $resource Resource name
     * @return array|null
     */
    protected function loadResource($resource)
    {
        $e                  = $this->event;
        $config             = $e->getParam('config');
        $moduleDirectory    = $e->getParam('directory');

        $resourceName = str_replace(
            ' ',
            '',
            ucwords(str_replace('_', ' ', $resource))
        );

        //$resourceName = ucfirst($resource);
        $resourceClass      = sprintf(
            'Module\\%s\Installer\Resource\\%s',
            ucfirst($moduleDirectory),
            $resourceName
        );
        if (!class_exists($resourceClass)) {
            $resourceClass = sprintf(
                '%s\Resource\\%s',
                __NAMESPACE__,
                $resourceName
            );
        }
        if (!class_exists($resourceClass)) {
            return;
        }
        $methodAction = $e->getParam('action') . 'Action';
        if (!method_exists($resourceClass, $methodAction)) {
            return;
        }
        $options = isset($config['resource'][$resource])
            ? $config['resource'][$resource] : array();
        /*
        if (is_string($options)) {
            $optionsFile = sprintf(
                '%s/%s/config/%s',
                Pi::path('module'),
                $moduleDirectory,
                $options
            );
            $options = include $optionsFile;
            if (empty($options) || !is_array($options)) {
                $options = array();
            }
        }
        */
        $resourceHandler = new $resourceClass($options);
        $resourceHandler->setEvent($this->event);
        $ret = $resourceHandler->$methodAction();

       if (is_bool($ret)) {
            $ret = array(
                'status'    => $ret,
                'message'   => array(),
            );
        } elseif (is_array($ret)) {
            if (!isset($ret['message'])) {
                $ret['message'] = array();
            } else {
                $ret['message'] = (array) $ret['message'];
            }
        } else {
            $ret = null;
        }

        if (null !== $ret) {
            array_unshift($ret['message'], 'Class: ' . $resourceClass);
        }

        return $ret;
    }
}
