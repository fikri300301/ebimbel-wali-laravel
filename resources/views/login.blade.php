<!-- resources/views/auth/login.blade.php -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <form action="{{ route('login.process') }}" method="POST">
            @csrf
            <div>
                <label for="email">Email</label>
                <input type="text" id="user_email" name="user_email" required>
            </div>
            <div>
                <label for="password">Password</label>
                <input type="password" id="user_password" name="user_password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        @if ($errors->any())
            <div>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</body>
</html>
