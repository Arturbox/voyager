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
            return collect(File::allFiles(base_path(config('voyager.models.namespace_separator', app()->getNamespace()))))->map(function($contact)  {

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
            return collect(File::allFiles(base_path(config('voyager.database.namespace_separator'))));
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
        $oldName = preg_replace('/s$/', '', $table_info['oldName']);
        $newName = preg_replace('/s$/', '', $table_info['name']);
        return $collection->map(function ($value) use ($oldName,$newName) {
            /*Checking statement, if there is a model name , wich contains a table name with uppercase, then renaming the model name to new name*/
            if (ucwords($oldName . '.php') === $value->getFilename()) {
                $content = preg_replace('/(class) (.*?) /i','$1 '.ucwords($newName).' ', $value->getContents());
                File::put($value->getPath() . '/' . $value->getFilename(), $content);
                File::move($value->getPath() . '/' . $value->getFilename(), $value->getPath() . '/' . ucwords($newName . '.php'));
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

if (!function_exists('delete_migration')) {
    function delete_migration($collection, $table_info)
    {
        return $collection->map(function ($value) use ($table_info) {
            /*Checking statement, if there is a migration name , wich contains a table old name, then reading content*/
            if (preg_match("/" . $table_info['oldName'] . "/", $file = $value->getPathname())) {
                unlink($file);
                return true;
            }
        });
    }
}

if (!function_exists('add_modal_scripts')) {
    function add_modal_scripts($dataFilters)
    {
        $script = '';
        foreach($dataFilters as $filter){

            $script .= '$(".btn-new-filter-'.$filter->id.'").click(function(){
                    $("#new_filter_'.$filter->id.'_modal").modal("show");
                });';

            if(!$filter->children->isEmpty())
                $script .= add_modal_scripts($filter->children);
        }
        return $script;
    }
}

if (!function_exists('classes_exists')) {
    function classes_exists($classes)
    {
        foreach($classes as $class){
            if (!class_exists($class))
                return false;
        }
        return true;
    }
}

if(!function_exists('update_model_translatable')) {
    function update_model_translatable($table)
    {
        try {
            $file = base_path() . '\\' . config('voyager.models.namespace') . ucwords(preg_replace('/s$/', '', $table['oldName'])) . '.php';
            // Find and replace in model_file
            if (file_exists($file)) {

                // Get translatable columns
                $translatable_columns = array_filter(array_map(function ($column) {
                    if (in_array(strtolower($column['type']['name']), config('voyager.multilingual.field_types')))
                        return $column['name'];
                }, $table['columns']));

                $new_content = preg_replace('/(.*?protected.*?\$translatable.*?\=.*?\[)(.*?)(\])/', '$1\'' .implode("', '", $translatable_columns). '\'$3', file_get_contents($file));

                file_put_contents($file, $new_content);
            }
        } catch (Exception $e) {

            return back()->with([
                'message' => 'Error changing translatable array in model.' . $e->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }
}

if(!function_exists('migrate_generate_column_fix')){
    function migrate_generate_column_fix($table_name){

        getAllMigrations()->map( function($migration) use ($table_name){

            if(preg_match('/create_'.$table_name.'_table.php/',$migration->getFilename())){

                $content = file_get_contents($migration_file = $migration->getRealPath());
                foreach (array_keys($params = config('voyager.migrate.params')) as $key){
                    $content =  preg_replace('/(->)('.$key.')(\()/','$1'.$params[$key].'$3', $content);
                }
                return file_put_contents($migration_file, $content);
            }
        });
    }
}