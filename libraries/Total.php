<?php

/**
 * Total class is used as a replacement for an associative arrays when counting a running total of something.
 * 
 * The advantage of using this class over an array is that:
 *
 * 1) Normally if using an associative array you have to use isset() before adding to the array, ie. $array['total_march'] += 4 will not work 'total_march' is not set, meaning messy code is required
 *
 * 2) Total automatically works out the running totals, so if we were calculating totals for each month of the year, ie. $total['jan'], $total['feb'], etc. we could work out the total for the whole year by simply calling $total->total()
 *
 * 3) The class is configurable to automatically number format or round the result, or add a prefix or suffix, eg. $45, 159 GBP
 *
 * It uses a dot notation to replace having to do long associative arrays so
 * $total['march']['first']['am] becomes $total['march.first.am'].
 *
 * This also allows us not to have to check if the value isset() (see above), and means that running
 * totals can be added to each section of the array.
 *
 * @package     Standard
 * @subpackage  Libraries
 * @author Leo Allen <leo.f.allen@gmail.com>
 * @requires Kohana v2.3.4
 */
class Total_Core implements Iterator, ArrayAccess, Countable {

	/**
	 * @var The total array, this stores all values for the total object
	 * @access protected
	 */
	protected $array = array();

	/**
	 * @var The configuration for this library, loaded from seperate config file and values passed into constructor
	 * @access protected
	 */
	protected $config;

	/**
	 * Creates a new Total instance.
	 *
	 * @param   array   array to pass in optionally
	 * @return  object
	 */
	public static function factory($config = array())
	{
		return new Total($config);
	}

	/**
	 * Sets the unique "any field" key and creates an ArrayObject from the
	 * passed array.
	 *
	 * @param   array   array to validate
	 * @return  void
	 */
	public function __construct($config = array())
	{
	   // Merge supplied config with the default settings
		$config += Kohana::config('total');

		// Assign config to this class
		$this->config = $config;

		$this->array = new Total_Result($config);
	}

	/**
	 * Overload setter to allow for setting of values even if none has been previously set.
	 *
	 * Uses dot notated system so that if we set $total['march.first.am'] it will set the am total, which will be nested inside the first array, which is nested inside the array for march
	 *
	 * @param $index
	 * @param $value
	 * @param $config If array passed through here will load this as the total result's config, rather than the config set for the Total Result as a whole
	 */
	public function offsetSet($index, $value, $config = array())
	{ 
		$config += $this->config;
		
		$value = (float) $value;

		// Create keys
		$keys = explode('.', $index);

		// Add total to array
		$this->array->add_total($value);

		// Set object to variable row thus creating a reference to it
		$row = $this->array;

		for ($i = 0, $end = count($keys) - 1; $i <= $end; $i++)
		{
			// Get the current key
			$key = $keys[$i];

			if ( ! isset($row[$key]) OR $i === $end)
			{
				if (isset($keys[$i + 1]))
				{
					// If there are more nested levels to go down, just make the value a total result object
					$row[$key] = new Total_Result($config);

					// Create total row
					$row[$key]->add_total($value);
				}
				elseif ( ! isset($row[$key]))
				{
					// Add the intial total, this is the last nested level
					$row[$key] = $value;
				}
				else
				{
					// Add the value to the already existing total object
					$row[$key] = $row->offsetGet($key, FALSE) + $value;
				}
			}
			elseif (isset($keys[$i + 1]))
			{
				// Add to total row
				$row[$key]->add_total($value);
			}

			// Go down a level, creating a new row reference
			$row = $row[$key];
		}
	}
	
	/**
	 * Returns the overall total
	 *
	 * @param $format If false will ignore any formatting passed through by config
	 */
	public function total($format = NULL)
	{
		return $this->array->total($format);
	}
	
	/**
	 * Overloads offsetGet, so that a Total_Result object is returned even if nothing is found.
	 * 
	 * This means we always get a Total_Result object returned no matter what, saving problems
	 * where no value is set, and we then try and perform elsewhere an operation on a non-object.
	 *
	 * @param $index The key to search the array for
	 * @return Total_Result
	 */
	public function offsetGet($index)
	{
		$index = explode('.', $index);

		// Set up result
		$result = $this->array;

		foreach ($index as $key)
		{
			if (isset($result[$key]))
			{
				$result = $result[$key];
			}
			else
			{
				return new Total_Result($this->config);
			}
		}
		
		return $result;
	}

	/**
	 * Countable: count
	 */
	public function count()
	{
		return $this->array->count();
	}

	/**
	 * Iterator: current
	 */
	public function current()
	{
		return $this->array->current();
	}

	/**
	 * Iterator: key
	 */
	public function key()
	{
		return $this->array->key();
	}

	/**
	 * Iterator: next
	 */
	public function next()
	{
		return $this->array->next();
	}

	/**
	 * Iterator: rewind
	 */
	public function rewind()
	{
		$this->array->rewind();
	}

	/**
	 * Iterator: valid
	 */
	public function valid()
	{
		return $this->array->valid();
	}

	/**
	 * ArrayAccess: offsetExists
	 */
	public function offsetExists($offset)
	{
		return $this->array->offsetExists($offset);
	}

	/**
	 * ArrayAccess: offsetUnset
	 *
	 * @throws  Kohana_Database_Exception
	 */
	public function offsetUnset($offset)
	{
		return $this->array->offsetUnset($offset);
	}

} // End Total_Core


/**
 * This object is returned when calling offsetSet above, it works exactly like a normal array object but with the option to get the total for that array as well simply by calling $total_result->total()
 *
 * @author Leo Allen <leo.f.allen@gmail.com>
 */
class Total_Result extends ArrayIterator {

	protected $total = 0;

	protected $config;

	public function __construct($config)
	{
		$this->config = $config;

		return parent::__construct();
	}

	public function __toString()
	{
		return $this->total();
	}
	
	/**
	 * Returns the overall total
	 *
	 * @param $format If FALSE will ignore formatting options in config
	 */
	public function total($format = NULL)
	{
		return $this->format_result($this->total, $format);
	}

	public function add_total($val)
	{
		$this->total += $val;
	}

	/**
	 * Recursively counts the items in the total result array
	 * If nothing is set then it will count all items in the array
	 *
	 * @param $recursion 1 = current layer 2 = this layer and next layer etc. NULL = all layers
	 */
	public function recursive_count($recursion = NULL)
	{
		$count = 0;
		$layer = 0;

		// Make a clone of this result object as we don't want to mess up the iterator
		$obj = clone $this;

		foreach ($obj as $item)
		{
			$this->_count_layer($item, $count, $layer, $recursion);
		}

		return $count;
	}

	protected function _count_layer($item, & $count, & $layer, $recursion = NULL)
	{
		// First indicate this is a new layer
		$layer++;

		// See if we have gone far, enough
		if ( ! $item instanceof Total_Result OR ($recursion !== NULL AND $layer == $recursion))
		{
			// Just add to count
			$count ++;
		}
		else
		{
			// Again clone to avoid mucking up iterator
			$obj = clone $item;

			foreach ($obj as $val)
			{
				$this->_count_layer($val, $count, $layer, $recursion);
			}
		}

		// Reduce layer value to indicate we are now returning back to normal layer
		$layer--;
	}

	/**
	 * Overload offsetGet to allow for formatting, which can be switched off by passing FALSE to the second argument
	 * @param $key The key to search in the array for
	 * @param $format Will only add formatting from config if set to TRUE
	 */
	public function offsetGet($key, $format = TRUE)
	{
		if ($this->offsetExists($key))
		{
			$result = parent::offsetGet($key);
		}
		else
		{
			$result = 0;
		}
		
		if ($format AND is_numeric($result))
		{
			return $this->format_result($result);
		}
		else
		{
			return $result;
		}
	}
	
	/**
	 * Formats result based on attributes passed through from config file
	 *
	 * @param $result The value to format
	 * @param $number_format If not NULL this will be the value used to determine number formatting
	 */
	public function format_result($result, $format = NULL)
	{
		if ($format === FALSE)
		{
			return $result;
		}
		
		if ($this->config['number_format'] !== FALSE)
		{
			$result = number_format($result, $this->config['number_format']);
		}
		elseif ($this->config['round'] !== FALSE)
		{
			$result = round($result, $this->config['round']);
		}

		return $this->config['prefix'].$result.$this->config['suffix'];
	}
} // End Total_Result