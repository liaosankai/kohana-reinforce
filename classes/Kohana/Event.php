<?php

defined('SYSPATH') OR die('No direct script access.');

/**
 * @license http://www.opensource.org/licenses/BSD-3-Clause New BSD License
 */
class Kohana_Event
{

    /**
     * Array of events.
     *
     * @var array
     */
    protected static $events = array();

    /**
     * Protected constructor since this is a static class.
     *
     * @access  protected
     */
    protected function __construct()
    {
        // Nothing here
    }

    //---------------------------------------------
    // Class methods
    //---------------------------------------------

    /**
     * Adds an event listener to the queue.
     *
     * @access  public
     * @param   string $name     Event name
     * @param   string $observer Observer name
     * @param   Closure $closure  Event handler
     */
    public static function register($name, $observer, Closure $closure)
    {
        if (array_key_exists($name, static::$events) === FALSE) {
            static::$events[$name] = array();
        }
        if (array_key_exists($observer, static::$events[$name]) === TRUE) {
            throw new Kohana_Exception('Trying to set existed observer :observer to :event event, event observer name mast be unique.', array(':observer' => $observer, ':event' => $name));
        }
        static::$events[$name][$observer] = $closure;
    }

    /**
     * Returns TRUE if an event listener is registered for the event and FALSE if not.
     *
     * @access  public
     * @param   string $name  Event name
     * @return  boolean
     */
    public static function registered($name)
    {
        return isset(static::$events[$name]);
    }

    /**
     * Clears all event listeners for an event.
     *
     * @access  public
     * @param   string $name  Event name
     */
    public static function clear($name)
    {
        unset(static::$events[$name]);
    }

    /**
     * Overrides an event.
     *
     * @access  public
     * @param   string $name     Event name
     * @param   Closure $closure  Event handler
     */
    public static function override($name, Closure $closure)
    {
        static::clear($name);

        static::register($name, $closure);
    }

    /**
     * Runs all closures for an event and returns an array
     * contaning the return values of each event handler.
     *
     * @access  public
     * @param   string $name    Event name
     * @param   array $params  (optional) Closure parameters
     * @return  array
     */
    public static function trigger($name, array $params = array())
    {
        $values = array();

        if (isset(static::$events[$name])) {
            foreach (static::$events[$name] as $event) {
                $values[] = call_user_func_array($event, $params);
            }
        }

        return $values;
    }

    /**
     * Runs all closures for an event and returns the result
     * of the first event handler.
     *
     * @access  public
     * @param   string $name    Event name
     * @param   array $params  (optional) Closure parameters
     * @return  mixed
     */
    public static function first($name, array $params = array())
    {
        $results = static::trigger($name, $params);

        return empty($results) ? null : $results[0];
    }

}
