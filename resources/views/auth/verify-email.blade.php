@extends('layouts.auth')

@section('content')
<email-verification-prompt
    user-email="{{ $userEmail ?? 'your email' }}">
</email-verification-prompt>
@endsection