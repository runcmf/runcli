<?php
/**
 * Copyright 2016 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


namespace RunCli\Adapter;

class Common
{
    protected function mapType($dbType)
    {
        $dbType = strtolower($dbType);
        if (!isset($this->doctrineTypeMapping[$dbType])) {
            //throw new \Exception("Unknown database type ".$dbType." requested, " .
            // get_class($this) . " may not support it.");
            return 'unknown';//FIXME
        }

        return $this->doctrineTypeMapping[$dbType];
    }

    /**
     * Given a table comment this method tries to extract a typehint for Doctrine Type, or returns
     * the type given as default.
     *
     * @param string $comment
     * @param string $currentType
     *
     * @return string
     */
    protected function extractDoctrineTypeFromComment($comment, $currentType)
    {
        if (preg_match("(\(DC2Type:([a-zA-Z0-9_]+)\))", $comment, $match)) {
            $currentType = $match[1];
        }

        return $currentType;
    }

    /**
     * @param string $comment
     * @param string $type
     *
     * @return string
     */
    protected function removeDoctrineTypeFromComment($comment, $type)
    {
        return str_replace('(DC2Type:' . $type . ')', '', $comment);
    }

    /**
     * Aggregates and groups the index results according to the required data result.
     *
     * @param array $tableIndexRows
     * @param string|null $tableName
     *
     * @return array
     */
    protected function getPortableTableIndexesList($tableIndexRows, $tableName = null)
    {
        $result = [];
        foreach ($tableIndexRows as $tableIndex) {
            $indexName = $keyName = $tableIndex['key_name'];
            if ($tableIndex['primary']) {
                $keyName = 'primary';
            }
            $keyName = strtolower($keyName);

            if (!isset($result[$keyName])) {
                $result[$keyName] = [
                    'name' => $indexName,
                    'columns' => [$tableIndex['column_name']],
                    'unique' => $tableIndex['non_unique'] ? false : true,
                    'primary' => $tableIndex['primary'],
                    'flags' => isset($tableIndex['flags']) ? $tableIndex['flags'] : [],
                    'options' => isset($tableIndex['where']) ? ['where' => $tableIndex['where']] : [],
                ];
            } else {
                $result[$keyName]['columns'][] = $tableIndex['column_name'];
            }
        }

//    $eventManager = $this->_platform->getEventManager();

        $indexes = [];
        foreach ($result as $indexKey => $data) {
            $index = null;
//      $defaultPrevented = false;
//
//      if (null !== $eventManager && $eventManager->hasListeners(Events::onSchemaIndexDefinition)) {
//        $eventArgs = new SchemaIndexDefinitionEventArgs($data, $tableName, $this->_conn);
//        $eventManager->dispatchEvent(Events::onSchemaIndexDefinition, $eventArgs);
//
//        $defaultPrevented = $eventArgs->isDefaultPrevented();
//        $index = $eventArgs->getIndex();
//      }

//      if ( ! $defaultPrevented) {
            $index = new Index(
                $data['name'],
                $data['columns'],
                $data['unique'],
                $data['primary'],
                $data['flags'],
                $data['options']
            );
//      }

            if ($index) {
                $indexes[$indexKey] = $index;
            }
        }

        return $indexes;
    }
}
