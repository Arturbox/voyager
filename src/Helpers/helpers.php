<?php

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        return TCG\Voyager\Facades\Voyager::setting($key, $default);
    }
}

if (!function_exists('menu')) {
    function menu($menuName, $type = null, array $options = [])
    {
        return TCG\Voyager\Facades\Voyager::model('Menu')->display($menuName, $type, $options);
    }
}

if (!function_exists('voyager_asset')) {
    function voyager_asset($path, $secure = null)
    {
        return asset(config('voyager.assets_path').'/'.$path, $secure);
    }
}

if (!function_exists('getAllModels')) {
    function getAllModels(){
        try {
            return collect(File::allFiles(base_path(config('voyager.models.namespace', app()->getNamespace()))))->map(function($contact)  {

                $contact->fullPath = config('voyager.models.namespace', app()->getNamespace()).substr($contact->getFilename(),0,-4);;

                return $contact;
            });
        } catch (Exception $e) {
            return back()->with($this->alertException($e, __('voyager::generic.update_failed')));
        }

    }
}

if (!function_exists('getAllMigrations')) {
    function getAllMigrations()
    {
        try {
            return collect(File::allFiles(base_path('/database/migrations')));
        } catch (Exception $e) {
            return back()->with($this->alertException($e, __('voyager::generic.update_failed')));
        }
    }
}

if (!function_exists('clear_model_after_table_delete')) {
    function clear_model_after_table_delete($condition,$table_name)
    {
        $table_name = preg_replace('/s$/','',$table_name);

        return $condition->map(function($value) use ($table_name)  {
            /*Checking statement, if name of "Model" equal to table name ,which started with uppercase*/
            if (ucwords($table_name.'.php') === $value->getFilename())
            {
                File::delete($value->getRealPath());
            }
        });
    }
}

if (!function_exists('clear_migration_after_table_delete')) {
    function clear_migration_after_table_delete($condition,$table_name)
    {

        $table_name = preg_replace('/(^.*?)(s)?$/','$1s',$table_name);

        return $condition->map(function($value) use ($table_name)  {
            /*Checking statement, is there a name of table in the name of migration file */
            if (preg_match("/".$table_name."/",$value->getFilename()))
            {
                $content = $value->getContents();
                /*Checking statement, is there a name of table,which starting with some string in the content of migration file */
                if (preg_match("/Schema::create\('".$table_name."/",$content))
                {
                    File::delete($value->getRealPath());
                }
            }
        });
    }
}

if (!function_exists('rename_model')) {
    function rename_model($collection, $table_info)
    {

        $table_info = preg_replace('/s$/', '', $table_info['oldName']);

        return $collection->map(function ($value) use ($table_info) {
            /*Checking statement, if there is a model name , wich contains a table name with uppercase, then renaming the model name to new name*/
            if (ucwords($table_info['oldName'] . '.php') === $value->getFilename()) {
                File::move($value->getPath() . '/' . $value->getFilename(), $value->getPath() . '/' . ucwords($table_info['name'] . '.php'));
            }
        });
    }
}

if (!function_exists('rename_migration')) {
    function rename_migration($collection, $table_info)
    {
        return $collection->map(function ($value) use ($table_info) {
            /*Checking statement, if there is a migration name , wich contains a table old name, then reading content*/
            if (preg_match("/" . $table_info['oldName'] . "/", $value->getFilename())) {

                $content = $value->getContents();

                $content = preg_replace("/" . $table_info['oldName'] . "/", "$1" . $table_info['name'], $content);

                File::put($value->getPath() . '/' . $value->getFilename(), $content);

                File::move($value->getPath() . '/' . $value->getFilename(), $value->getPath() . '/' . preg_replace('/' . $table_info['oldName'] . '/', $table_info['name'], $value->getFilename()));
            }
        });
    }
}