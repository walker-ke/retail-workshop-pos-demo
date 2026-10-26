@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
<section class="management-page">
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/edit_products.css')}}?v={{ filemtime(public_path('assets/css/edit_products.css')) }}">
@endpush
<div class="head">
    <p style="position: relative; bottom: 20px;">Edit Products</p>
</div>
<div class="container" style="position: relative; left: 290px; top: 20px;">
    <div class="card">
        <div class="card-header bg-white">
            <div class="grid-container">
                <div class="grid-item"><a href="{{ route('list_product') }}" class="btn btn-outline-secondary float-right" >Go to Product List <i class="fa-solid fa-arrow-right"></i></a></div>
            </div>

        </div>
        <div class="card-body">
            <form action="{{ url('/update-product/'.$prod->id) }}" method="POST">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-6">
                    <label for="">Product Name</label>
                    <input type="text" class="form-control" id="product_name" name="product_name" value ="{{$prod->product_name}}" placeholder="Enter Product Name" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="inputState">Product Brand</label>
                        @php
                        $brand= DB::table('brands')->get();
                        @endphp
                        <select name="brand" class="form-control">
                            <option selected>{{$prod->brand}}</option>
                            @foreach($brand as $row)
                            <option value="{{ $row->brand_name }}">{{$row->brand_name}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                <div class="form-group col-md-2">
                    <label for="">Product Code</label>
                    <input type="text" class="form-control" id="product_code" value="{{$prod->product_code}}" name="product_code" placeholder="Enter Product Code" required>
                </div>
                <div class="form-group col-md-2" >
                    <label for="">Price</label>
                    <input type="number" class="form-control" id="price" value="{{$prod->price}}" name="price" placeholder="Enter Product Price" required>
                </div>
                </div>
                <button type="submit" class="btn btn-primary">Update Product</button>
            </form>
        </div>
    </div>
</div>
</section>
@endsection
