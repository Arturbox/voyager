<select class="form-control select2" name="relationship_model{{(isset($relationship['field'])?'_'.$relationship['field']:'')}}" >
    <option value="">-- None --</option>
    @if(isset($filesNames) && !empty($filesNames))
    @foreach($filesNames as $fileName)
    @if($dataType->model_name != $fileName->fullPath)
    <option @if(isset($relationshipDetails->model) && $relationshipDetails->model == $fileName->fullPath) {{ 'selected="selected"' }} @endif  value="{{$fileName->fullPath}}" >{{$fileName->getFilename()}}</option>
    @endif
    @endforeach
    @endif
</select>