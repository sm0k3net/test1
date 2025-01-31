<?php

namespace X4\Classes;

class Event {

}

class XEventMachine extends \xSingleton
{
    public $firedEvents;
    protected $_callbacks = array();
    protected $lastReturn = null;

    /**
     *  определяет действие по событию
     * @param string $eventName before@module:event  after@module:event   module:event
     * @param string $callback method
     * @param $callbackContext objectContext
     */

    public function on($eventName, $callback, $callbackContext)
    {
        $this->_callbacks[$eventName][] = array(
            'context' => $callbackContext,
            'callback' => $callback
        );
    }

    /**
     * дерегестрация события
     * @param string $eventName - удаляет все события
     * @param string $callback - если указано удаляет только данный callback c этго события
     */
    public function unregister($eventName, $callback = null)
    {
        if (!empty($callback)) {
            foreach ($this->_callbacks[$eventName] as $k => $v) {
                if ($this->_callbacks[$eventName][$k]['callback'] == $callback) {
                    unset($this->_callbacks[$eventName][$k]['callback']);
                }
            }
        } else {
            unset($this->_callbacks[$eventName]);
        }
    }

    /**
     *  генерирует событие
     * @param string $eventName
     * @param array $data - данные отправляемые по событию
     */
    public function fire($eventName, $data = null)
    {
        if (!empty($this->_callbacks[$eventName])) {
            $lastReturn = null;

            foreach ($this->_callbacks[$eventName] as $callback) {

                if (method_exists($callback['context'], $callback['callback'])) {
                    if ($lastReturn) $data = $lastReturn;

                    $this->firedEvents[] = array('event' => $eventName, 'callbank' => $callback['callback']);

                    if ($return = call_user_func_array(array(
                        $callback['context'],
                        $callback['callback']
                    ), array(
                        array(
                            'context' => $callback['context'],
                            'data' => &$data,
                            'event' => $eventName
                        )
                    ))
                    ) {
                        if (is_array($return)) {
                            $lastReturn = $return;
                        }

                    }

                } else {
                    trigger_error('event function not defined ' . get_class($callback['context']) . '-' . $callback['callback']);
                }
            }

            return $lastReturn;

        }
    }




    public function fireEvent($eventName, $data = null)
    {
        if (!empty($this->_callbacks[$eventName])) {
            $lastReturn = null;

            foreach ($this->_callbacks[$eventName] as $callback) {

                if (method_exists($callback['context'], $callback['callback'])) {
                    if ($lastReturn) $data = $lastReturn;

                    $this->firedEvents[] = array('event' => $eventName, 'callbank' => $callback['callback']);

                    if ($return = call_user_func_array(array(
                        $callback['context'],
                        $callback['callback']
                    ), array(
                        array(
                            'context' => $callback['context'],
                            'data' => &$data,
                            'event' => $eventName
                        )
                    ))
                    ) {
                        if (is_array($return)) {
                            $lastReturn = $return;
                        }

                    }

                } else {
                    trigger_error('event function not defined ' . get_class($callback['context']) . '-' . $callback['callback']);
                }
            }

            return $lastReturn;

        }
    }
}
