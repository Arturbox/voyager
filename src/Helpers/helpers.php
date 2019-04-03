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