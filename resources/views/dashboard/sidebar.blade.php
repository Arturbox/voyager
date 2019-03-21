<div class="side-menu sidebar-inverse">
    <nav class="navbar navbar-default" role="navigation">
        <div class="side-menu-container">
            <div class="navbar-header">
                <a class="navbar-brand" href="{{ route('voyager.dashboard') }}">
                    <div class="logo-icon-container">
                        <?php $admin_logo_img = Voyager::setting('admin.icon_image', ''); ?>
                        @if($admin_logo_img == '')
                            <img src="{{ voyager_asset('images/logo-icon-light.png') }}" alt="Logo Icon">
                        @else
                            <img src="{{ Voyager::image($admin_logo_img) }}" alt="Logo Icon">
                        @endif
                    </div>
                    <div class="title">{{Voyager::setting('admin.title', 'VOYAGER')}}</div>
                </a>
            </div><!-- .navbar-header -->

            <div class="panel widget center bgimage"
                 style="background-image:url({{ Voyager::image( Voyager::setting('admin.bg_image'), voyager_asset('images/bg.jpg') ) }}); background-size: cover; background-position: 0px;">
                <div class="dimmer"></div>
                <div class="panel-content">
                    <img src="{{ $user_avatar }}" class="avatar" alt="{{ Auth::user()->name }} avatar">
                    <h4>{{ ucwords(Auth::user()->name) }}</h4>
                    <p>{{ Auth::user()->email }}</p>

                    <a href="{{ route('voyager.profile') }}" class="btn btn-primary">{{ __('voyager::generic.profile') }}</a>
                    <div style="clear:both"></div>
                </div>
            </div>
        </div>
        <ul class="nav navbar-nav">
            <li class="dropdown">
                <a href="#5-dropdown-lang" data-toggle="collapse" aria-expanded="false" target="_self" style="color:">
                    <span class="icon voyager-tools"></span>
                    <span class="title">{{ trans('languages.'. App::getLocale()) }}</span>
                </a>
                <div id="5-dropdown-lang" class="panel-collapse collapse ">
                    <div class="panel-body">
                        <ul class="nav navbar-nav">
                            @foreach (config('voyager.multilingual.locales') as $language)
                                @if ($language != App::getLocale())
                                    <li class="">
                                        <a href="{{ route('langroute', $language) }}" target="_self" style="color:">
                                            <span class="icon voyager-compass"></span>
                                            <span class="title">{{ trans('languages.'. $language) }}</span>
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </div>
            </li>
        </ul>
        {!! menu('admin', 'admin_menu') !!}
    </nav>
</div>
