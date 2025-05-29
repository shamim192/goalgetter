@extends('backend.app')

@section('title', 'System settings')

@section('content')
    <!--begin::Toolbar-->
    <div class="toolbar" id="kt_toolbar">
        <div class=" container-fluid  d-flex flex-stack flex-wrap flex-sm-nowrap">
            <!--begin::Info-->
            <div class="d-flex flex-column align-items-start justify-content-center flex-wrap me-2">
                <!--begin::Title-->
                <h1 class="text-dark fw-bold my-1 fs-2">
                    Dashboard <small class="text-muted fs-6 fw-normal ms-1"></small>
                </h1>
                <!--end::Title-->

                <!--begin::Breadcrumb-->
                <ul class="breadcrumb fw-semibold fs-base my-1">
                    <li class="breadcrumb-item text-muted">
                        <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">
                            Home </a>
                    </li>

                    <li class="breadcrumb-item text-muted"> Setting </li>
                    <li class="breadcrumb-item text-muted"> System Settings </li>

                </ul>
                <!--end::Breadcrumb-->
            </div>
            <!--end::Info-->
        </div>
    </div>
    <!--end::Toolbar-->

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card-style mb-4">
                    <form method="POST" action="{{ route('system.update') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mt-4">
                                <div class="input-style-1">
                                    <label for="title">Title:</label>
                                    <input type="text" placeholder="Enter Title" id="title"
                                        class="form-control @error('title') is-invalid @enderror" name="title"
                                        value="{{ $setting->title ?? '' }}" />
                                    @error('title')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6 mt-4">
                                <div class="input-style-1">
                                    <label for="email">Email:</label>
                                    <input type="email" placeholder="Enter Email" id="email"
                                        class="form-control @error('email') is-invalid @enderror" name="email"
                                        value="{{ $setting->email ?? '' }}" />
                                    @error('email')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mt-4">
                                <div class="input-style-1">
                                    <label for="system_name">System Name:</label>
                                    <input type="text" placeholder="System Name" id="system_name"
                                        class="form-control @error('system_name') is-invalid @enderror" name="system_name"
                                        value="{{ $setting->system_name ?? '' }}" />
                                    @error('system_name')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6 mt-4">
                                <div class="input-style-1">
                                    <label for="copyright_text">Copy Rights Text:</label>
                                    <input type="text" placeholder="Copy Rights Text" id="copyright_text"
                                        class="form-control @error('copyright_text') is-invalid @enderror"
                                        name="copyright_text" value="{{ $setting->copyright_text ?? '' }}" />
                                    @error('copyright_text')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mt-4">
                                <div class="input-style-1">
                                    <label for="logo">Logo:</label>
                                    <input type="file" class="dropify @error('logo') is-invalid @enderror" name="logo"
                                        id="logo"
                                        data-default-file="@isset($setting){{ asset($setting->logo) }}@endisset" />
                                </div>
                                @error('logo')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="col-md-6 mt-4">
                                <div class="input-style-1">
                                    <label for="favicon">Favicon:</label>
                                    <input type="file" class="dropify @error('favicon') is-invalid @enderror"
                                        name="favicon" id="favicon"
                                        data-default-file="@isset($setting){{ asset($setting->favicon) }}@endisset" />
                                </div>
                                @error('favicon')
                                    <span class="text-danger" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mt-4">
                                <div class="input-style-1">
                                    <label for="description">About System:</label>
                                    <textarea placeholder="Type here..." id="description" name="description"
                                        class="form-control @error('description') is-invalid @enderror">
                                        {{ $setting->description ?? '' }}
                                    </textarea>
                                    @error('description')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary">Submit</button>
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-danger me-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        ClassicEditor
            .create(document.querySelector('#description'))
            .catch(error => {
                console.error(error);
            });
    </script>
@endpush
