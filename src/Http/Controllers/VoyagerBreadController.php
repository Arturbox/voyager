<?php

namespace TCG\Voyager\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use TCG\Voyager\Database\Schema\Column;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Database\Schema\Table;
use TCG\Voyager\Database\Types\Type;
use TCG\Voyager\Events\BreadAdded;
use TCG\Voyager\Events\BreadDeleted;
use TCG\Voyager\Events\BreadUpdated;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Models\DataFilter;
use TCG\Voyager\Models\DataTable;
use TCG\Voyager\Models\DataTableRows;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Translator\Collection;
use Response;

class VoyagerBreadController extends Controller
{
    public function index()
    {
        Voyager::canOrFail('browse_bread');

        $dataTypes = Voyager::model('DataType')->select('id', 'name', 'slug')->get()->keyBy('name')->toArray();

        $tables = array_map(function ($table) use ($dataTypes) {
            $table = [
                'name'       => $table,
                'slug'       => isset($dataTypes[$table]['slug']) ? $dataTypes[$table]['slug'] : null,
                'dataTypeId' => isset($dataTypes[$table]['id']) ? $dataTypes[$table]['id'] : null,
            ];

            return (object) $table;
        }, SchemaManager::listTableNames());

        return Voyager::view('voyager::tools.bread.index')->with(compact('dataTypes', 'tables'));
    }

    /**
     * Create BREAD.
     *
     * @param Request $request
     * @param string  $table   Table name.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create(Request $request, $table)
    {
        Voyager::canOrFail('browse_bread');

        $dataType = Voyager::model('DataType')->whereName($table)->first();

        $data = $this->prepopulateBreadInfo($table);
        $data['fieldOptions'] = SchemaManager::describeTable((isset($dataType) && strlen($dataType->model_name) != 0)
            ? app($dataType->model_name)->getTable()
            : $table
        );

        return Voyager::view('voyager::tools.bread.edit-add', $data);
    }

    private function prepopulateBreadInfo($table)
    {
        $displayName = Str::singular(implode(' ', explode('_', Str::title($table))));
        $modelNamespace = config('voyager.models.namespace', app()->getNamespace());
        if (empty($modelNamespace)) {
            $modelNamespace = app()->getNamespace();
        }

        return [
            'isModelTranslatable'  => true,
            'table'                => $table,
            'slug'                 => Str::slug($table),
            'display_name'         => $displayName,
            'display_name_plural'  => Str::plural($displayName),
            'model_name'           => $modelNamespace.Str::studly(Str::singular($table)),
            'generate_permissions' => true,
            'show_filters' => false,
            'server_side' => false,
        ];
    }

    /**
     * Store BREAD.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        try {
            $dataType = Voyager::model('DataType');
            $res = $dataType->updateDataType($request->all(), true);
            $data = $res
                ? $this->alertSuccess(__('voyager::bread.success_created_bread'))
                : $this->alertError(__('voyager::bread.error_creating_bread'));
            if ($res) {
                event(new BreadAdded($dataType, $data));
            }

//            activity($dataType->name)
//                ->performedOn($dataType)
//                ->causedBy(\Auth::user())
//                ->withProperties($request->all())
//                ->log($request->getMethod());

            return redirect()->route('voyager.bread.index')->with($data);
        } catch (Exception $e) {
            return redirect()->route('voyager.bread.index')->with($this->alertException($e, 'Saving Failed'));
        }
    }

    /**
     * Edit BREAD.
     *
     * @param string $table
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($table)
    {
        Voyager::canOrFail('browse_bread');

        $dataType = Voyager::model('DataType')->whereName($table)->first();

        $fieldOptions = SchemaManager::describeTable((strlen($dataType->model_name) != 0)
            ? app($dataType->model_name)->getTable()
            : $dataType->name
        );

        $isModelTranslatable = is_bread_translatable($dataType);
        $tables = SchemaManager::listTableNames();
        $dataTypeRelationships = Voyager::model('DataRow')->where('data_type_id', '=', $dataType->id)->where('type', '=', 'relationship')->get();

        /***********
        SMART TABLE
         *********/
        if (isset($dataType->id)){
            $dataTables = DataTable::where('data_type_id', '=',$dataType->id)->get();
            $dataTables->map(function ($dataTable) use($dataType){
                $dataTableContent = strlen($dataType->model_name) != 0 ? app($dataType->model_name)->orderBy('order')->get() : call_user_func([DB::table($dataType->name), "get"]);
                if ($dataTableContent->count())
                    $dataTableContent = $dataTableContent->sortBy('order');
                return $dataTable->reverseBySmart($dataType,$dataTableContent);
            });
        }
        /********************/
        return Voyager::view('voyager::tools.bread.edit-add',
            compact('dataType',
                'fieldOptions',
                'isModelTranslatable',
                'tables',
                'dataTypeRelationships',
                'dataTables'
                ));
    }



    /**
     * Update BREAD.
     *
     * @param \Illuminate\Http\Request $request
     * @param number                   $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function update(Request $request, $id)
    {
        Voyager::canOrFail('browse_bread');

        /* @var \TCG\Voyager\Models\DataType $dataType */
        try {
            $dataType = Voyager::model('DataType')->find($id);

            // Prepare Translations and Transform data
            $translations = is_bread_translatable($dataType)
                ? $dataType->prepareTranslations($request)
                : [];


            $res = $dataType->updateDataType($request->all(), true);
            $data = $res
                ? $this->alertSuccess(__('voyager::bread.success_update_bread', ['datatype' => $dataType->name]))
                : $this->alertError(__('voyager::bread.error_updating_bread'));
            if ($res) {
                event(new BreadUpdated($dataType, $data));
            }

            // Save translations if applied
            $dataType->saveTranslations($translations);

            return redirect()->route('voyager.bread.index')->with($data);
        } catch (Exception $e) {
            return back()->with($this->alertException($e, __('voyager::generic.update_failed')));
        }
    }

    /**
     * Delete BREAD.
     *
     * @param Number $id BREAD data_type id.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        Voyager::canOrFail('browse_bread');

        /* @var \TCG\Voyager\Models\DataType $dataType */
        $dataType = Voyager::model('DataType')->find($id);

        $dataRows = Voyager::model('DataRow')->where('data_type_id',$id)->get();

        // Delete Translations, if present
        if (is_bread_translatable($dataType)) {
            $dataType->deleteAttributeTranslations($dataType->getTranslatableAttributes());
            foreach ($dataRows as $row){
                $row->deleteAttributeTranslations($row->getTranslatableAttributes());
            }
        }

        $res = Voyager::model('DataType')->destroy($id);
        $data = $res
            ? $this->alertSuccess(__('voyager::bread.success_remove_bread', ['datatype' => $dataType->name]))
            : $this->alertError(__('voyager::bread.error_updating_bread'));
        if ($res) {
            event(new BreadDeleted($dataType, $data));
        }

        if (!is_null($dataType)) {
            Voyager::model('Permission')->removeFrom($dataType->name);
        }

        return redirect()->route('voyager.bread.index')->with($data);
    }

    // ************************************************************
    //  _____      _       _   _                 _     _
    // |  __ \    | |     | | (_)               | |   (_)
    // | |__) |___| | __ _| |_ _  ___  _ __  ___| |__  _ _ __  ___
    // |  _  // _ \ |/ _` | __| |/ _ \| '_ \/ __| '_ \| | '_ \/ __|
    // | | \ \  __/ | (_| | |_| | (_) | | | \__ \ | | | | |_) \__ \
    // |_|  \_\___|_|\__,_|\__|_|\___/|_| |_|___/_| |_|_| .__/|___/
    //                                                  | |
    //                                                  |_|
    // ************************************************************

    /**
     * Add Relationship.
     *
     * @param Request $request
     */
    public function addRelationship(Request $request)
    {
        $relationshipField = $this->getRelationshipField($request);
        if (!class_exists($request->relationship_model) and (is_array($request->relationship_chain_model) and !classes_exists($request->relationship_chain_model)) ) {
            return back()->with([
                'message'    => 'Model Class '.$request->relationship_model.' does not exist. Please create Model before creating relationship.',
                'alert-type' => 'error',
            ]);
        }

        try {
            DB::beginTransaction();

            $relationship_column = $request->relationship_column_belongs_to;
            if ($request->relationship_type == 'hasOne' || $request->relationship_type == 'hasMany') {
                $relationship_column = $request->relationship_column;
            }

            if ($request->relationship_type == 'oneInChain'){
                $relationshipDetails = [
                    'model'       => $request->relationship_chain_model,
                    'table'       => $request->relationship_chain_table,
                    'type'        => $request->relationship_type,
                    'column'      => $relationship_column,
                    'key'         => $request->relationship_keyChain,
                    'label'       => $request->relationship_chain_label,
                    'pivot_table' => $request->relationship_pivot,
                    'pivot'       => '1',
                    'taggable'    => $request->relationship_taggable,
                ];
            }
            else{
                // Build the relationship details
                $relationshipDetails = [
                    'model'       => $request->relationship_model,
                    'table'       => $request->relationship_table,
                    'type'        => $request->relationship_type,
                    'column'      => $relationship_column,
                    'key'         => $request->relationship_key,
                    'label'       => $request->relationship_label,
                    'pivot_table' => $request->relationship_pivot,
                    'pivot'       => ($request->relationship_type == 'belongsToMany') ? '1' : '0',
                    'taggable'    => $request->relationship_taggable,
                ];
            }

            $newRow = new DataRow();

            $newRow->data_type_id = $request->data_type_id;
            $newRow->field = $relationshipField;
            $newRow->type = 'relationship';
            $newRow->display_name = $request->relationship_table;
            $newRow->required = 0;

            foreach (['browse', 'read', 'edit', 'add', 'delete'] as $check) {
                $newRow->{$check} = 1;
            }

            $newRow->details = $relationshipDetails;
            $newRow->order = intval(Voyager::model('DataType')->find($request->data_type_id)->lastRow()->order) + 1;

            if (!$newRow->save()) {
                return back()->with([
                    'message'    => 'Error saving new relationship row for '.$request->relationship_table,
                    'alert-type' => 'error',
                ]);
            }

            DB::commit();

            return back()->with([
                'message'    => 'Successfully created new relationship for '.$request->relationship_table,
                'alert-type' => 'success',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with([
                'message'    => 'Error creating new relationship: '.$e->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }

    /**
     * Get Relationship Field.
     *
     * @param Request $request
     *
     * @return string
     */
    private function getRelationshipField($request)
    {
        // We need to make sure that we aren't creating an already existing field

        $dataType = Voyager::model('DataType')->find($request->data_type_id);

        $field = str_singular($dataType->name).'_'.$request->relationship_type.'_'.str_singular($request->relationship_table).'_relationship';

        $relationshipFieldOriginal = $relationshipField = strtolower($field);

        $existingRow = Voyager::model('DataRow')->where('field', '=', $relationshipField)->first();
        $index = 1;

        while (isset($existingRow->id)) {
            $relationshipField = $relationshipFieldOriginal.'_'.$index;
            $existingRow = Voyager::model('DataRow')->where('field', '=', $relationshipField)->first();
            $index += 1;
        }

        return $relationshipField;
    }

    /**
     * Delete Relationship.
     *
     * @param Number $id Record id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteRelationship($id)
    {
        Voyager::model('DataRow')->destroy($id);

        return back()->with([
                'message'    => 'Successfully deleted relationship.',
                'alert-type' => 'success',
            ]);
    }

    // ************************************************************
    //        FILTERS
    //
    // ************************************************************

    /**
     * Add Relationship.
     *
     * @param Request $request
     */
    public function addFilter(Request $request)
    {
        try {
            DB::beginTransaction();

            // Build the relationship details
            $newFilter = new DataFilter();
            $newFilter->data_type_id = $request->data_type_id;
            $newFilter->data_type_parent_id = $request->data_type_parent_id;
            $newFilter->display_name = $request->display_name;
            $newFilter->display_field = $request->display_field;
            $newFilter->parent_id = $request->parent_id??$request->parent_id=null;
            $newFilter->order = intval(Voyager::model('DataFilter')
                    ->where('data_type_parent_id',$newFilter->data_type_parent_id)
                    ->where('parent_id',$newFilter->parent_id)->get()->max('order')) + 1;
            if (!$newFilter->save()) {
                return back()->with([
                    'message'    => 'Error saving new filter for '.$request->display_name,
                    'alert-type' => 'error',
                ]);
            }

            DB::commit();

            return back()->with([
                'message'    => 'Successfully created new filter for '.$request->display_name,
                'alert-type' => 'success',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with([
                'message'    => 'Error creating new filter: '.$e->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }

    /**
     * Delete Filter.

     *
     * @param Number $id Record id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteFilter($id)
    {
        Voyager::model('DataFilter')->destroy($id);

        return back()->with([
            'message'    => 'Successfully deleted Filter.',
            'alert-type' => 'success',
        ]);
    }

    public function update_order(Request $request)
    {
        $filterOrder = json_decode($request->input('order'));
        $this->orderFilter($filterOrder, null);
    }


    private function orderFilter($filters, $parentId)
    {
        foreach ($filters as $index => $filter) {

            $item = Voyager::model('DataFilter')->findOrFail($filter->id);
            $item->order = $index + 1;
            $item->parent_id = $parentId;
            $item->save();

            if (isset($filter->children)) {
                $this->orderFilter($filter->children, $item->id);
            }
        }
    }


    /**
     * Add Relationship.
     *
     * @param Request $request
     */
    public function saveSmartTable(Request $request)
    {
        try {
            DB::beginTransaction();
            $dataTable = new DataTable();
            $dataTable->data_type_id = $request->input('data_type_id');
            $dataTable->name = $request->input('name');
            if (!$dataTable->save()) {
                return back()->with([
                    'message'    => 'Error saving new smart table',
                    'alert-type' => 'error',
                ]);
            }
            DB::commit();

            //add saving data in activity log data
//            activity($dataTable->name)
//                ->performedOn($dataTable)
//                ->causedBy(\Auth::user())
//                ->withProperties($request->all())
//                ->log($request->getMethod());

            return back()->with([
                'message'    => 'Successfully created new smart table',
                'alert-type' => 'success',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with([
                'message'    => 'Error creating new filter: '.$e->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }

    public function addDataTableRelationshipColumn(Request $request,$id)
    {
        try {
            DB::beginTransaction();
            $action = $request->post('action');

            if( $action == 'smartTableFields'){
                $dataTypeRelation = Voyager::model('DataTable')->where('id', '=', $request->post('smart_id'))->first();
                $dataTypeRelation->slug = $dataTypeRelation->dataType->slug;
            }
            else{
                $dataTypeRelation = Voyager::model('DataType')->where('slug', '=', $request->input('slug'))->first();
                $dataTypeRelationContent = strlen($dataTypeRelation->model_name) != 0 ? app($dataTypeRelation->model_name)->get() : call_user_func([DB::table($dataTypeRelation->name), "get"]);
            }

            $dataTable =  Voyager::model('DataTable')->find($id);
            $requestRow = $action == 'tableFields' ? $request->input('row') : [1];
            $requestFieldCount = $action == 'tableFields'?$request->field_count:1;
            $i = 0;
            for ($j = 0; $j<$requestFieldCount; $j++){
                foreach ($requestRow as $id){
                    $row = !empty($dataTypeRelation->rows)? $dataTypeRelation->rows->where('field',$request->input('field'))->first():$dataTypeRelation->columns->where('id', $request->input('column_id'))->first();
                    $order = intval($request->input('order'));
                    $dataTableRelationshipRows = new DataTableRows();
                    $dataTableRelationshipRows->order = $order++;
                    $dataTableRelationshipRows->data_table_id = $dataTable->id;
                    $dataTableRelationshipRows->field = $dataTypeRelation->slug.'_relationship_'.$request->input('field');
                    $dataTableRelationshipRows->type ='relationship';
                    $dataTableRelationshipRows->display_name = $row->display_name;
                    if ($request->input('row')){
                        $rowData = $dataTypeRelationContent->where('id',$id)->first();
                        $dataTableRelationshipRows->display_name = $rowData->translate(App()->getLocale())->{$request->input('field')};
                    }

                    if ($action == 'tableValues' || $action == 'smartTableFields'){
                        $rowInfo = (object)['column'=>$request->input('field'),'relationshipTable' => $request->input('dataTypeRelationship')];

                        if ($action == 'smartTableFields'){

                            $relationshipField =  $dataTypeRelation->columns->where('type','relationship')->where('details.slug', $request->post('dataTypeRelationship'))->first()->field;
                            $rowInfo->relationshipBindColumn =$dataTypeRelation->columns->where('field', $relationshipField)->first()->details->column;
                            $rowInfo->relationshipColumn = $dataTable->columns->where('type','relationship')->where('details.slug', $request->post('dataTypeRelationship'))->first()->details->column;
                            $rowInfo->column = $row->field;
                        }
                        elseif ($action == 'tableValues'){

                            $dataTypeRelationshipBind = Voyager::model('dataType')->where('slug',$request->post('dataTypeRelationship'))->first();

                            $rowInfo->relationshipField = $dataTypeRelationshipBind->rows->where('type','relationship')->where('details.table',$dataTypeRelation->slug)->first()->field;
                        }
                    }
                    elseif($action == 'smartTableFields') $rowInfo = (object) ['column'=> $row->field ];
                    else                                  $rowInfo = (object)['column'=>$request->input('field')];
                    $details = [
                        'type' => $request->input('type'),
                        'slug'=> $request->input('slug')? $request->input('slug'):$dataTypeRelation->slug,
                        'row_info' => $rowInfo,
                        'relationship_type' => $action,
                    ];
                    if($request->post('smart_id') && $action == 'smartTableFields'){
                        $details['smart_id'] = $request->post('smart_id');
                        $details['column'] = $request->input('save_in');
                    }
                    if ($request->input('dataTypeRow') && $action != 'tableValues'){
                        $details['column'] = $request->input('dataTypeRow')[$i];
                    }
                    if ($request->input('row') && $action != 'tableValues'){
                        $rowInfo->id = $id;
                        $dataTableRelationshipRows->field.='_'.$id.'_'.$j;
                    }
                    $dataTableRelationshipRows->details = $details;
                    $result[] = $dataTableRelationshipRows->save()?1:0;
                    $i++;
                }
            }

            if ( array_sum($result) ==  count($requestRow)*$requestFieldCount ){
                DB::commit();
                return back()->with([
                    'message'    => 'Successfully created new smart table Field',
                    'alert-type' => 'success',
                ]);
            }
            else{
                DB::rollBack();

                return back()->with([
                    'message'    => 'Error creating new Field: '.$e->getMessage(),
                    'alert-type' => 'error',
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with([
                'message'    => 'Error creating new filter: '.$e->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }



    public function columnData(Request $request)
    {
        // GET THE DataType based on the slug
        $dataType = Voyager::model('DataType')->where('slug', '=', $request->input('slug'))->first();
        $model = app($dataType->model_name);
        $query = $model::select('*');
        $column = $request->input('column');
        $dataTypeContent = call_user_func([$query->latest($model::CREATED_AT), 'get']);
        $columnData = [];
        foreach ($dataTypeContent as $data){
            //$columnData[$data->id] =['id' => $data->id, 'value' => $data->translate(App()->getLocale())->{$column},'column' => $column,'header'=>$dataType->translate(App()->getLocale())->display_name_singular ];
            $columnData[$data->id] = $data->translate(App()->getLocale())->{$column};
        }
        exit(json_encode($columnData));
    }


    public function updateDataTable(Request $request,$id)
    {
        Voyager::canOrFail('browse_bread');

        /* @var \TCG\Voyager\Models\DataType $dataType */
        try {
            $dataTable = Voyager::model('DataTable')->find($id);

            $dataType = $dataTable->dataType()->first();

            $dataTableRows = $dataTable->columns()->get();

            // Prepare Translations and Transform data
            $translations = is_bread_translatable($dataTable)
                ? $dataTable->prepareTranslations($request)
                : [];


            $res = $dataTable->updateDataTable($dataType,$request->all(), true);
            $data = $res
                ? $this->alertSuccess(__('voyager::bread.success_update_bread', ['datatype' => $dataType->name]))
                : $this->alertError(__('voyager::bread.error_updating_bread'));

            if ($res) {
                event(new BreadUpdated($dataType, $data));
            }

            // Save translations if applied
            $dataTable->saveTranslations($translations);

            foreach ($dataTableRows as $row){
                foreach ($row->getTranslatableAttributes() as $attribute) {
                    $request->request->add([ $attribute => $request->{'field_'.$attribute.'_'.$row->field} ]);
                    $request->request->add([ $attribute.'_i18n' => $request->{'field_'.$attribute.'_'.$row->field.'_i18n'} ]);
                }
                $translationsRow = is_bread_translatable($row)
                    ? $row->prepareTranslations($request)
                    : [];
                $row->saveTranslations($translationsRow);
                unset($request->{'field_'.$attribute.'_'.$row->field});
                unset($request->{'field_'.$attribute.'_'.$row->field.'_i18n'});
            }

//            activity($dataTable->dataType->slug)
//                ->performedOn($dataTable->dataType)
//                ->causedBy(\Auth::user())
//                ->withProperties($request->all())
//                ->log($request->getMethod());

            return redirect()->route('voyager.bread.index')->with($data);
        } catch (Exception $e) {
            return back()->with($this->alertException($e, __('voyager::generic.update_failed')));
        }

    }

    public function saveRelationData(Request $request)
    {
        try {
            DB::beginTransaction();
            // GET THE DataType based on the slug
            $dataType = Voyager::model('DataType')->where('slug', '=', $request->input('slug'))->first();

            if ($dataTable = $dataType->tables()->where('id', $request->input('table_id'))->where('data_type_id',$dataType->id )->first()){
                $rows = [];
                $dataRows = new DataTableRows();
                foreach ($request->input('tableData') as $column=>$value){
                    $dataRows->name = $column;
                    $dataRows->data_table_id = $dataTable->id;
                    $rows[] =['name'=>$column,'data_table_id'=>$dataTable->id,'value'=> json_encode($value)];
                }
                $dataRows->insert($rows);
            }
            DB::commit();
            exit(true);
        } catch (\Exception $e) {
            DB::rollBack();
            exit(false);
        }
        exit(json_encode($columnData));
    }


    public function getSmartRelationsCompare(Request $request)
    {
        $data = json_decode($request->data);
        $dataType = Voyager::model('DataType')->whereName($request->table)->first();
        $dataType2 = Voyager::model('DataType')->whereName($data->dataType)->first();

        $relations = $dataType2->rows->where('type','relationship')->map(function ($item) use ($dataType, $dataType2)  {
            $fields = $dataType->rows->where('type','relationship')->pluck('details')->pluck('column','table')->toArray();
            if (array_key_exists($item->details->table,$fields))
            {
                return ['dataType' => $item->details->table, 'column'=>$fields[$item->details->table], 'show_field'=>$item->details->label];
            }
        })->filter()->values()->toArray();

        if(!empty($relations))
            return Response::json( [ 'status'=>true, 'relations'=>$relations] );
        else
            return Response::json( ['message'=>'No relations', 'status'=>false] );

    }


    public function saveSmartGroupTable(Request $request){
        try{

            $dataTable = DataTable::find($request->data_table_id);

            if($dataTable->updateSmartTableGroups($request->all())){
                return back()->with([
                    'message'    => 'Successfully updated Smart Table Groups:.',
                    'alert-type' => 'success',
                ]);

            }
            return back()->with([
                'message'    => 'Error while updating Smart Table Groups:',
                'alert-type' => 'error',
            ]);

        }catch (Exception $e){
            return back()->with([
                'message'    => 'Error while updating Smart Table Groups: '.$e->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }

    public function saveSmartColWidth ( Request $request ) {
        try {
            DB::beginTransaction();

            $data = json_decode($request->post('data'));

            $dataTable = Voyager::model('DataTable')->where('id',$request->post('table_id'))->first();

            $browseRows = $dataTable->browseRows->where('details.nesthead','>',-1);

            $hiddenFields = Voyager::model('DataTable')->hiddenFields;
            $groupRows = $dataTable->groupRows->pluck('details.column')->toArray();

            $data  = collect($data)->whereNotIn('field',$hiddenFields)->whereNotIn('field',$groupRows);


            $data->map(function ($field) use($browseRows,&$array){
                $column = $browseRows->where('field',$field->field)->first()
                    ??$browseRows->where('details.column',$field->field)->first()
                    ??false;
                if ($column){
                    $details = $column->details;
                    $details->width = $field->width;
                    Voyager::model('DataTableRows')->where('id', $column->id)->update(['details' => json_encode($details)]);
                }
                return true;
            });
            DB::commit();

            return Response::json( ['message'   => 'Successfully updated smart column width.',
                'alert-type' => 'success'] );

        } catch (\Exception $e) {
            DB::rollBack();

            return Response::json( ['message'    => 'Error updating smart column width.',
                'alert-type' => 'error'] );
        }
    }



    public function saveSmartData(Request $request)
    {
        try{
            $DataTable = DataTable::find($request->table_id);

            $response = $DataTable->saveSmartData($request->all());

            if(isset($response['status']) && $res = $response['status'] ){

                $log_data                  = $response['log_data'];
                $log_data['redirect_data'] = $response['redirect'];

                activity($DataTable->dataType->slug)
                    -> performedOn($DataTable->dataType)
                    -> causedBy(\Auth::user())
                    -> withProperties($log_data)
                    -> tap(function ($activity) {
                        $activity->type = 'data_table';
                    })
                    -> log($request->getMethod());
            }
            return Response::json( $res ? ['message'    => 'Successfully updated Smart-table.',
                'alert-type' => 'success' ] :
                ['message'    => 'Error while updating Smart-table.',
                    'alert-type' => 'error'   ]);
        }catch (Exception $e){

            return  Response::json([
                'message'    => 'An exception was occured while updating Smart-table:'.$e->getMessage(),
                'alert-type' => 'error',
            ]);
        }
    }
}
