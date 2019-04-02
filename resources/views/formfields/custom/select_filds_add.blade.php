<select class="form-control select2" name="{{$selected_fil_name}}" >
    <option value="">-- None --</option>
    @if(isset($filesNames) && !empty($filesNames))
    @foreach($filesNames as $fileName)
    @if($current_file_name != $fileName->fullPath)
    <option value="{{$fileName->fullPath}}" >{{$fileName->getFilename()}}</option>
    @endif
    @endforeach
    @endif
</select>