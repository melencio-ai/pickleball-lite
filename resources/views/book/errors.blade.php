@if($errors->any())
    <div class="notice error booking-notice" role="alert">
        <x-booking-icon name="circle-alert" />
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
