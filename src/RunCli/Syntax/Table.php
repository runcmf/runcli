<?php namespace RunCli\Syntax;

use Illuminate\Database\Capsule\Manager as DB;

class Table// extends \Way\Generators\Syntax\Table
{

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * @param array $fields
	 * @param string $table
	 * @param string $method
	 * @return string
	 */
	public function run(array $fields, $table, $method = 'table')
	{
//		$table = substr($table, strlen(DB::getTablePrefix()));
		$this->table = $table;
		$compiled = $this->compile($this->getTemplate(), ['TABLE'=>$table,'METHOD'=>$method]);
		$t =  $this->replaceFieldsWith($this->getItems($fields), $compiled);
//die($t);
    return $t;
	}

	/**
	 * Return string for adding all foreign keys
	 *
	 * @param array $items
	 * @return array
	 */
	protected function getItems(array $items)
	{
		$result = array();
		foreach($items as $item) {
			$result[] = $this->getItem($item);
		}
		return $result;
	}

	/**
	 * @param array $item
	 * @return string
	 */
	//abstract protected function getItem(array $item);

	/**
	 * @param $decorators
	 * @return string
	 */
	protected function addDecorators($decorators)
	{
		$output = '';
		foreach ($decorators as $decorator) {
			$output .= sprintf('->%s', $decorator);
			// Do we need to tack on the parens?
			if (strpos($decorator, '(') === false) {
				$output .= '()';
			}
		}
		return $output;
	}
  /**
   * Fetch the template of the schema
   *
   * @return string
   */
  protected function getTemplate()
  {
    return file_get_contents(__DIR__.'/../templates/schema.txt');
  }


  /**
   * Replace $FIELDS$ in the given template
   * with the provided schema
   *
   * @param $schema
   * @param $template
   * @return mixed
   */
  protected function replaceFieldsWith($schema, $template)
  {
    return str_replace('$FIELDS$', implode(PHP_EOL.'            ', $schema), $template);
  }

  public function compile($template, $data)
  {
    foreach($data as $key => $value)
    {
      $template = preg_replace("/\\$$key\\$/i", $value, $template);
    }

    return $template;
  }
}
