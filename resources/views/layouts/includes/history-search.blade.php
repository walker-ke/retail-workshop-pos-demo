<form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-3" role="search">
<label class="flex-grow-1">Search history<input class="form-control" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="Receipt number, customer or cashier"></label>
<label>Show entries<select class="form-select" name="per_page">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected((int)request('per_page',50)===$size)>{{ $size }}</option>@endforeach</select></label>
<button class="btn btn-primary" type="submit">Search</button>
</form>
