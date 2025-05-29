<div id="kt_header" class="header " data-kt-sticky="true" data-kt-sticky-name="header"
    data-kt-sticky-offset="{default: '200px', lg: '300px'}">

    <div class=" container-fluid  d-flex align-items-stretch justify-content-between">
        <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
            <div class="d-flex align-items-center d-lg-none">
                <div class="btn btn-icon btn-active-color-primary ms-n2 me-1 " id="kt_aside_toggle">
                    <i class="ki-duotone ki-abstract-14 fs-1"><span class="path1"></span><span
                            class="path2"></span></i>
                </div>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="d-lg-none">
                <img alt="Logo" src="{{ asset($systemSetting->logo ?? 'backend/images/logo.svg') }}"
                    class="mh-40px" />
            </a>
            <div class="btn btn-icon w-auto ps-0 btn-active-color-primary d-none d-lg-inline-flex me-2 me-lg-5 "
                data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
                data-kt-toggle-name="aside-minimize">

                <i class="ki-duotone ki-black-left-line fs-1 rotate-180"><span class="path1"></span><span
                        class="path2"></span></i>
            </div>
        </div>

        <div class="d-flex align-items-stretch justify-content-end flex-lg-grow-1">

            <div class="d-flex align-items-stretch flex-shrink-0">

                <div class="d-flex align-items-center ms-2 ms-lg-3" id="kt_header_user_menu_toggle">
                    <div class="cursor-pointer symbol symbol-35px symbol-lg-35px"
                        data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent"
                        data-kt-menu-placement="bottom-end">
                        <img alt="Pic" src="{{ asset(Auth::user()->avatar ?? 'backend/images/profile.jpeg') }}" />
                    </div>
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px"
                        data-kt-menu="true">
                        <div class="menu-item px-3">
                            <div class="menu-content d-flex align-items-center px-3">
                                <div class="symbol symbol-50px me-5">
                                    <img alt="Logo"
                                        src="{{ asset(Auth::user()->avatar ?? 'backend/images/profile.jpeg') }}" />
                                </div>
                                <div class="d-flex flex-column">
                                    <div class="fw-bold d-flex align-items-center fs-5">
                                        {{ auth()->user()->name }}
                                    </div>

                                    <a href="#" class="fw-semibold text-muted text-hover-primary fs-7">
                                        {{ auth()->user()->email }} </a>
                                </div>
                            </div>
                        </div>
                        <div class="separator my-2"></div>
                        <div class="menu-item px-5">
                            <a href="{{ route('profile.setting') }}" class="menu-link px-5">
                                My Profile
                            </a>
                        </div>
                        <div class="menu-item px-5 my-1">
                            <a href="{{ route('system.index') }}" class="menu-link px-5">
                                Account Settings
                            </a>
                        </div>
                        <div class="menu-item px-5">
                            <a href="#"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                                class="menu-link px-5">
                                Sign Out
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                @csrf
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
