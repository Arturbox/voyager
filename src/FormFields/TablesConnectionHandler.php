<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 3/26/2019
 * Time: 12:26 PM
 */

namespace TCG\Voyager\FormFields;

class TablesConnectionHandler extends AbstractHandler
{
    protected $codename = 'tables_connection';

    public function createContent($row, $dataType, $dataTypeContent, $options)
    {
        return view('voyager::formfields.tables_connection', [
            'row'             => $row,
            'options'         => $options,
            'dataType'        => $dataType,
            'dataTypeContent' => $dataTypeContent,
        ]);
    }
}
