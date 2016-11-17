<?php namespace RunCli\Syntax;

class DroppedTable
{
    /**
     * Get string for dropping a table
     *
     * @param $tableName
     * @return string
     */
    public function drop($tableName)
    {
        return "DB::schema()->drop('$tableName');";
    }
}