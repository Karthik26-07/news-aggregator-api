<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
        }
        .container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .success { color: green; font-size: 1.2em; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email Verification</h1>
        @if ($verified ?? false)
            <p class="success">Your email has been successfully verified!</p>
            <p>You can now log in to the News Aggregator API using your credentials.</p>
        @else
            <p style="color: red;">Email verification failed. The link may be invalid or expired.</p>
            <p>Please try registering again or contact support.</p>
        @endif
    </div>
</body>
</html>