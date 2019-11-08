<?php

namespace TCG\Voyager\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Traits\Translatable;
use TCG\Voyager\Models\DataFilter;
use \Illuminate\Support\Facades\Schema;

class DataTable extends Model
{
    use Translatable;
    protected $table = 'data_tables';

    protected $translatable = ['name'];

    protected $fillable = [
        'name',
        'data_type_id ',
        'nesthead'
    ];

    protected $guarded = [];

    public $timestamps = false;

    protected $hiddenFields = ['id','exec_formula'];

    public function columns()
    {
        return $this->hasMany(Voyager::modelClass('DataTableRows'))->orderBy('order');
    }

    public function exceptGroupColumns(){
        return $this->columns()->where('type','<>', 'groupBy');
    }

    public function groupRows(){
        return $this->columns()->where('type','groupBy');
    }

    public function browseRows()
    {
        return $this->columns()->where('browse', 1);
    }

    public function readRows()
    {
        return $this->columns()->where('read', 1);
    }

    public function editRows()
    {
        return $this->columns()->where('edit', 1);
    }

    public function addRows()
    {
        return $this->columns()->where('add', 1);
    }

    public function deleteRows()
    {
        return $this->columns()->where('delete', 1);
    }

    public function lastColumn()
    {
        return $this->columns->last()->order;
    }

    public function dataType(){
        return $this->belongsTo(Voyager::modelClass('DataType'));
    }

    public function updateDataTable($dataType, $requestData, $throw = false)
    {
        try {
            DB::beginTransaction();

            // Prepare data
//            foreach (['generate_permissions', 'server_side','show_filters','filter_browse','filter_read','filter_update','filter_add'] as $field) {
//                if (!isset($requestData[$field])) {
//                    $requestData[$field] = 0;
//                }
//            }
            if ($this->fill($requestData)->save()) {
                $fields = $this->fields((strlen($dataType->model_name) != 0)
                    ? app($dataType->model_name)->getTable()
                    : array_get($requestData, 'name')
                );

                $requestData = $this->getRelationships($requestData, $fields);

                foreach ($fields as $field) {
                    $dataRow = $this->columns()->firstOrNew(['field' => $field]);

                    foreach (['browse', 'read', 'edit', 'add', 'delete'] as $check) {
                        $dataRow->{$check} = isset($requestData["field_{$check}_{$field}"]);
                    }

                    $dataRow->required = boolval($requestData['field_required_'.$field]);
                    $dataRow->field = $requestData['field_'.$field];
                    $dataRow->type = $requestData['field_input_type_'.$field];
                    $dataRow->details = json_decode($requestData['field_details_'.$field]);
                    $dataRow->display_name = $requestData['field_display_name_'.$field];
                    $dataRow->order = intval($requestData['field_order_'.$field]);

                    if (!$dataRow->save()) {
                        throw new \Exception(__('voyager::database.field_safe_failed', ['field' => $field]));
                    }
                }

                // Clean data_rows that don't have an associated field
                // TODO: need a way to identify deleted and renamed fields.
                //   maybe warn the user and let him decide to either rename or delete?
                $this->columns()->whereNotIn('field', $fields)->delete();

//                // It seems everything was fine. Let's check if we need to generate permissions
//                if ($this->generate_permissions) {
//                    Voyager::model('Permission')->generateFor($this->name);
//                }

                DB::commit();

                return true;
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($throw) {
                throw $e;
            }
        }

        return false;
    }



    public function fields($name = null)
    {
        if (is_null($name)) {
            $name = $this->name;
        }

        $fields = SchemaManager::listTableColumnNames($name);

        if ($extraFields = $this->extraFields()) {
            foreach ($extraFields as $field) {
                $fields[] = $field['Field'];
            }
        }

        return $fields;
    }

    public function getRelationships($requestData, &$fields)
    {
        if (isset($requestData['relationships'])) {
            $relationships = $requestData['relationships'];
            if (count($relationships) > 0) {
                foreach ($relationships as $index => $relationship) {
                    // Push the relationship on the allowed fields
                    array_push($fields, $relationship);

                    $requestData['field_details_'.$relationship] = $requestData['relationship_details_'.$relationship];
                }
            }
        }

        return $requestData;
    }

    public function extraFields()
    {
        if (empty(trim($this->model_name))) {
            return [];
        }

        $model = app($this->model_name);
        if (method_exists($model, 'adminFields')) {
            return $model->adminFields();
        }
    }

    public function getNameFromNumber($num) {
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return $this->getNameFromNumber($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }


    public function reverseBySmart($dataType,$dataTableContent){
        // Nesthead Grouped Smart tables columns
        $browseRows = $this->browseRows->where('details.nesthead','>',-1);
        $exceptGroupRows = $browseRows->whereNotIn('field',$this->hiddenFields)->whereNotIn('field',$this->groupRows->pluck('details.column')->toArray())->groupBy('details.nesthead')->sortKeys();
        $browseRows = $browseRows->groupBy('details.nesthead')->sortKeys();
        $this->rowsByContent = $browseRows->collapse();

        // Smart tables columns
        $this->dataContent = $this->mergeSmartSelectedValues($dataTableContent);

        // Data stored in smart table
        $this->mergedListRows = $this->mergedSelectListInRows()->collapse();

        //add empty headers
        $this->nestHeaders = collect();
        $nestParent = [];
        $nestChildrenTable = [];
        $nestChildrenTableField = [];

        $nestHead = $this->nesthead?Voyager::model('DataType')->where('id',$this->nesthead)->first():false;
        $exceptGroupRows->map(function ($columns,$id) use($nestHead,&$nestParent,&$nestChildrenTable,&$nestChildrenTableField){
            if ($id){
                $nestHeadDataTypeContent = strlen($nestHead->model_name) != 0 ? app($nestHead->model_name)->where('id',$id)->first() : call_user_func([DB::table($nestHead->name), "get"])->where('id',$id)->first();
                $nestParent[$id] = (object)['title'=>$nestHeadDataTypeContent->translate(App()->getLocale())->name, 'colspan'=> $columns->count()];
            }
            else{
                $nestParent[$id] = (object)['title'=>'', 'colspan'=> $columns->count()];
            }
            //table names
            $columns->map(function ($column) use (&$nestChildrenTable,&$nestChildrenTableField){
                if (isset($column->details->slug)){
                    $dataTypeRelation = Voyager::model('DataType')->where('slug', '=', $column->details->slug)->first();
                    if (isset($nestChildrenTable[$column->details->slug])){
                        $nestChildrenTable[$column->details->slug]->colspan = $nestChildrenTable[$column->details->slug]->colspan+1;
                    }
                    else{
                        $nestChildrenTable[$column->details->slug] = (object)[ 'title'=>$dataTypeRelation->translate(App()->getLocale())->display_name_singular, 'colspan'=> 1 ];
                    }
                }
                else{
                    $nestChildrenTable[] = (object)[ 'title'=>'', 'colspan'=> 1 ];
                }
                $nestChildrenTableField[] = (object)[ 'title'=>$column->translate(App()->getLocale())->display_name, 'colspan'=> 1 ];
            });

        });
        $this->nestHeaders->push(array_values($nestParent));
        $this->nestHeaders->push(array_values($nestChildrenTable));
        //$this->nestHeaders->push($nestChildrenTableField);


        $this->hiddenRows = $this->mergedListRows->map(function ($column,$key){
            return isset($column->details->hidden)? '"#spreadsheet-'.$this->id.' thead tr:not(.jexcel_nested) td[data-x='.$column->order.']"':false;
        })->filter(function ($column){
            return $column;
        });

        $groups = $this->columns->where('type','groupBy');
        if ($groups->count()){
            $groupsData = $groups->pluck('details');
            $dataByGroup = collect([]);
            $this->dataContent->groupBy($groupsData->pluck('column')->toArray())->recursiveGroups($groupsData->toArray(),$this->mergedListRows,$dataByGroup,count($this->hiddenFields));
            $this->dataContent = $dataByGroup;
            $this->groups = $groupsData->map(function ($group){
                $dataTypeGroup = Voyager::model('DataType')->where('slug', '=', $group->slug)->first();
                $dataTypeGroupContent = strlen($dataTypeGroup->model_name) != 0 ? app($dataTypeGroup->model_name)->get() : call_user_func([DB::table($dataTypeGroup->name), "get"]);
                return (object)['dataContent'=>$dataTypeGroupContent,'field'=>$group->show_field, 'dataType'=> $group->slug];
            });
            $this->mergedListRows = $this->mergedListRows->except($this->groupRows->pluck('details.column')->toArray());
        }

        $this->recordIds = $dataTableContent->pluck('id');
        // $this->showHiddenColumns = $this->mergedListRows->map(function ($item,$key) {
        //     return '"#spreadsheet-'.$this->id.' thead tr:not(.jexcel_nested) td[data-x='.$key.']"';
        // });

        $this->formula();

        $this->computationGroupField = $this->getComputationFields('computationGroupField')->first();
        $this->computationFields = $this->getComputationFields('computationFields');
        $this->computationToMultiple = $this->getComputationToMultiple('computationToMultiple');

        return $this;
    }

    public function formula()
    {
        $this->letterFields = $this->rowsByContent->map(function ($row,$i){
            return $this->getNameFromNumber($i);
        });
        $this->rowsByContent->where("details.formula")->map(function ($row) {
            $formula = $row->details->formula;
            $formulaGroup = isset($row->details->formulaField) ? $this->getFormulaGroup($formula) : false;

            if ($formulaGroup == 'continue')
                return $row;

            $this->dataContent->map(function ($data, $key) use( &$formula, $formulaGroup, $row ){

                if ($formulaGroup && isset($data['groupCount']) && $data['field'] == $row->details->formulaField)
                    $formula = isset($formulaGroup[$data['id']]) ? $formulaGroup[$data['id']] : $formulaGroup[key($formulaGroup)];

                if ($data->has($row->field))
                {
                    if ($data->has('exec_formula') && $execFormula = json_decode($data['exec_formula']) ){
                        if (isset($execFormula->{$row->field}))
                            return $data;
                    }
                    $this->setFormula($data, $formula, $row, $key);
                }

                return $data;
            });
        });
    }

    public function setFormula (&$data, $formula,$row, $key) {
        $data->put($row->field, preg_replace_callback("/[a-zA-Z]+/",function ($input) use ($key, $formula){
            if ($this->letterFields->search($input[0])!==false)
                return $input[0].($key+1);
            return $input[0];
        },$formula));
    }

    public function getFormulaGroup($formula){
        try{
            return eval("return $formula;");
        }
        catch (\ParseError $e){
            return 'continue';
        }
    }

    public function getComputationFields($param){
        $columns = $this->rowsByContent->where('details.'.$param,true);
        return $columns->map(function ($column){
            if ($column->type == 'relationship' && isset($column->details->column))
                return $column->details->column;
            return $column->field;
        });
    }
    public function getComputationToMultiple($param){
        $column = $this->rowsByContent->where('type','relationship')->where('details.type','dropdown')->where('details.'.$param,true)->first();
        return $this->mergedListRows[$column->field]->source->map(function ($data) use($column){
            $data['column'] = $column->details->column;
            return $data;
        });
    }


    public function getDropdownBindData($columnDetails, $columnBindDetails,$tableDataType, $tableDataRowDetails)
    {
        if( $tableDataRowDetails->type == 'belongsToMany' )
        {
            $dataContent = strlen($tableDataType->model_name) != 0 ? app($tableDataType->model_name)->get() : call_user_func([DB::table($tableDataType->name), "get"]);
            return $dataContent->map(function($data) use($tableDataRowDetails){
                return collect([$data->id=>$data->belongsToMany($tableDataRowDetails->model,$tableDataRowDetails->pivot_table)->get()]);
            });
        }
        elseif($tableDataRowDetails->type == 'belongsTo')
        {
        }
        elseif( $tableDataRowDetails->type == 'hasMany')
        {
        }
    }

    public function fieldBindOtherField($column,$array){
        $columnDetails = $column->details;
        $columnBind = $this->rowsByContent->where('field',$columnDetails->relationship);

        if($column->details->type == 'autocomplete'){
            $columnBindDetails = $columnBind->first()->details;
            $tableDataType = Voyager::model('DataType')->where('slug', '=', $columnDetails->slug)->first();
            $tableDataRowDetails = $tableDataType->rows->where('type', 'relationship')->where('details.table', $columnBindDetails->slug)->first()->details;

            $this->getDropdownBindData($columnDetails, $columnBindDetails, $tableDataType,
                $tableDataRowDetails);


            $array[key($array)]->bindData = $this->getDropdownBindData($columnDetails, $columnBindDetails,$tableDataType,
                $tableDataRowDetails)->map(function ($groupData) use ($column){
                return collect([$groupData->keys()->first()=>$groupData->first()->map(function($data) use ($column){
                    return (object)['id'=>$data->id,'name'=>$data->translate(App()->getLocale())->{$column->details->row_info->column}];
                })]);
            });
        }
        elseif($column->details->type == 'dropdown'){
            $array[key($array)]->order_relation = $columnBind->keys()->first()-$this->groupRows->count();
        }
        return  $array;
    }



    public function mergedSelectListInRows()
    {
        return $this->rowsByContent->map(function($column,$i){
            $array[$column->field] = (object)[
                'type'=>$column->type != 'relationship'?$column->type:$column->details->type,
                'display_name'=>$column->display_name,
                'details'=>$column->details,
                'order' =>$i,
            ];

            if ($column->type == 'relationship' && in_array($column->details->type,['dropdown','autocomplete']) ){
                $dataTypeRelation = Voyager::model('DataType')->where('slug', '=', $column->details->slug)->first();
                $dataTypeRelationContent = strlen($dataTypeRelation->model_name) != 0 ? app($dataTypeRelation->model_name)->get() : call_user_func([DB::table($dataTypeRelation->name), "get"]);
                $array[$column->field]->source = $dataTypeRelationContent->map(function ($attr) use($column){
                    return  ['id'=>$attr->id,'name'=>$attr->translate(App()->getLocale())->{$column->details->row_info->column}];
                });
            }
            return isset($column->details->relationship)?$this->fieldBindOtherField($column,$array):$array;
        });

    }

    public function mergeSmartSelectedValues($dataTableContent)
    {
        return $dataTableContent->map(function($data){
            return $this->rowsByContent->map(function ($column) use ($data){

                if (array_key_exists($column->field, $data->attributes))
                    return [$column->field=>$data->translate(App()->getLocale())->{$column->field}];

                elseif($column->type == 'relationship' && isset($column->details->smart_id) ){
                    $dataTableRelation = Voyager::model('DataTable')->where('id', $column->details->smart_id)->first();
                    $data =app($dataTableRelation->dataType->model_name)->where($column->details->row_info->relationshipBindColumn , $data->{$column->details->row_info->relationshipColumn})->get();
                    return empty($data->count()) ? [$column->field => null] : [$column->field => $data->pluck($column->details->row_info->column)->sum()];
                }
                elseif (isset($column->details->column) && $column->type == 'relationship'){
                    return [$column->field=>$data->{$column->details->column}];
                }
                elseif($column->type == 'relationship' && isset($column->details->row_info->id)){
                    $dataTypeRelation = Voyager::model('DataType')->where('slug', '=', $column->details->slug)->first();
                    $data = strlen($dataTypeRelation->model_name) != 0
                        ? app($dataTypeRelation->model_name)->where('id',$column->details->row_info->id)->first()
                        : call_user_func([DB::table($dataTypeRelation->name), "get"]);
                    return [$column->field=>$data->{$column->details->row_info->column}];
                }
                elseif ($column->type == 'relationship' && isset($column->details->row_info->relationshipTable) && isset($column->details->row_info->relationshipField)){
                    $slug = $column->details->slug;
                    $dataType =  Voyager::model('DataType')->where('slug', '=', $slug)->first();
                    $dataTypeContent = strlen($dataType->model_name) != 0 ? app($dataType->model_name)->get() : call_user_func([DB::table($dataType->name), "get"]);

                    $slugBind = $column->details->row_info->relationshipTable;

                    $select = (object)['tables' => [$slugBind => $data->{$column->details->row_info->relationshipField}]];
                    if ($result = DataFilter::relatedDataFiltering($select, $dataTypeContent, $slug)->first())
                        return [$column->field=>$result->translate(App()->getLocale())->{$column->details->row_info->column}];
                    else
                        return [$column->field=>''];
                }
            })->collapse();
        });

    }


    public function updateSmartTableGroups( $requestData , $throw = false )
    {
        try {
            $groups = $this->columns()->where('type', 'groupBy');
            $oldFields = $groups->pluck('field')->toArray();
            $order = $this->lastColumn();

            foreach ($requestData['table'] as $key => $data)
            {
                if ($data = json_decode($data)){
                    $field = $data->dataType.'_groupBy_'.$data->column;
                    if( ($key = array_search($field, $oldFields)) !== false )
                        unset($oldFields[$key]);
                    $data = [
                        'data_table_id' => $this->id,
                        'field'         => $field,
                        'display_name'  => $field,
                        'type'          => 'groupBy',
                        'order'         => ++$order,
                        'details'       => json_encode([
                            'slug'       => $data -> dataType,
                            'column'     => $data -> column,
                            'show_field' => $data -> show_field,
                            'type'    => $requestData['type'][$key]
                        ]),
                    ];
                    $this->columns()->updateOrInsert(['field' => $field], $data);
                }
            }
            if(!empty($oldFields))
                $this->columns()->whereIn('field', $oldFields)->delete();

            return true;
        }catch (\Exception $e){
            if ($throw) throw $e;
        }
    }


    public function relationshipField()
    {
        return @$this->details->column;
    }



    public function setDetailsAttribute($value)
    {
        $this->attributes['details'] = json_encode($value);
    }

    public function getDetailsAttribute($value)
    {
        return json_decode(!empty($value) ? $value : '{}');
    }

    public function saveSmartData($data)
    {
//        try{
        DB::beginTransaction();
        $redirectData = false;

        $model = $this->dataType->model_name;

        // Checking for redirected table data
        if(isset($data['redirect_table'])){
            $redirectTableColumn = $this->dataType->rows->where('type','relationship')->where('details.table',array_key_first($data['redirect_table']))->first()->details->column;
            $redirectData        = [$redirectTableColumn => $data['redirect_table'][array_key_first($data['redirect_table'])]];
        }

        // Grabbing id-s from all rows of db table
        $oldFields = $model::query()->when($redirectData,function ($query) use($redirectData){
            return $query->where($redirectData);
        })->pluck('id')->toArray();

        $translatable = is_bread_translatable(app($model));

        for ($i = 0 ; $i < (isset($data['data']) ? count($data['data']):0) ; $i++) {

            //Get the row if exist or create a new one
            $id = $data['data'][$i]['id'];
            $instance = app($model);
            if ($id){
                $instance = app($model)->withTrashed()->where('id',$id)->first();
            }

            // Remove updated from $oldFields id list
            if( ($key = array_search($id, $oldFields)) !== false )
                unset($oldFields[$key]);

            // Filtrum enq, vercnum enq miayn ayn fieldery voronq bazayi tvyal table-um en pahvum
            $keys = array_diff(Schema::getColumnListing($this->dataType->slug), ['id']);
            $save_data = array_filter($data['data'][$i], function ($key) use ($keys) {
                return in_array($key, $keys);
            }, ARRAY_FILTER_USE_KEY);

            // Correction of data for saving
            if (!empty($redirectData))
                $save_data = array_merge($save_data, $redirectData);
            $save_data['order'] = $i+1;
            $save_data['deleted_at'] = null;


            // Get translatable fields and translations for row
            if ($translatable){
                $transFields = isset($data['data'][$i]['lang']) ? $data['data'][$i]['lang'] : $this->saveWithoutTranslatable( $instance);

                if (!isset($data['data'][$i]['lang']))
                    $transFields = $this->updateTranslatabeFields($transFields,$save_data);

                $translations = $this->getTranslationsfromData($instance, $transFields);

                $data['data'][$i]['lang'] = $transFields;

                $save_data = $this->updateSaveData($instance,$transFields,$save_data);

            }
            $this->saveWithoutFillable($instance, $save_data);

            $instance->save();

            if ( $translatable && isset($translations) && $translations) $instance->saveTranslations($translations);


            // Preparing data for activity log
            $data['data'][$i] = isset($data['data'][$i]['lang'])? array_merge($instance->toArray(),['lang' => $data['data'][$i]['lang']]):$instance->toArray();
        }

        if(!empty($oldFields)) $model::whereIn('id', $oldFields)->delete();

        DB::commit();

        return ['status' => true , 'redirect' => $redirectData , 'log_data' => $data];

//        }catch (\Exception $e){
//
//            DB::rollBack();
//            if ($throw) throw $e;
//        }
    }

    public function getTranslationsfromData($instance, $lang)
    {
        try{
            $my_request = new Request();
            $my_request->setMethod('POST');
            $my_request->request->add($lang);

            return $instance->prepareTranslations($my_request);

        }catch(\Exception $e){
            throw $e;
        }
    }


    public function saveWithoutTranslatable($instance)
    {
        $localeFileds = [];
        foreach ($instance->getTranslatableAttributes() as $translarableField){
            $localeFileds[$translarableField.'_i18n'] = $instance->id ? json_encode(array_map(function ($v){
                if($v == null) return '';
                return $v;
            },$instance->getTranslationsOf($translarableField) ))
                : json_encode(array_map(function($v){ return '';},array_flip(config('voyager.multilingual.locales'))));
        }
        return $localeFileds;
    }


    public function updateTranslatabeFields($transFields,$save_data){
        foreach ($transFields as $field => $value) {
            $locate = \App::getLocale() ? \App::getLocale() : config('voyager.multilingual.default');
            $tmp_lang = json_decode($value, true);
            $tmp_lang [$locate] = $save_data[str_replace( '_i18n','',$field)]? $save_data[str_replace( '_i18n','',$field)]:'';
            $transFields[$field] = json_encode($tmp_lang);
        }
        return $transFields;
    }


    public function saveWithoutFillable(&$instance, $save_data)
    {
        foreach ($save_data as $field => $value) {
            $instance->$field = (is_array($value) || is_object($value))?json_encode($value):$value;
        }
        return $instance;
    }

    public function updateSaveData($instance,$transFields,$data){
        $locate = \App::getLocale() ? \App::getLocale() : config('voyager.multilingual.default');
        $default = config('voyager.multilingual.default');
        foreach ($instance->getTranslatableAttributes() as $translarableField){
            if (array_key_exists($translarableField."_i18n",$transFields)){
                $fieldData = json_decode($transFields[$translarableField."_i18n"]);
                if ($default != $locate)
                    $data[$translarableField] = $fieldData->$default;
            }
        }
        return $data;
    }
}
