[TOC]

# Preface

As a PHP application, The Arsse requires the aid of a Web server in order to communicate with clients. How to install an configure a Web server in general is outside the scope of this document, but this section provides examples and advice for Web server configuration specific to The Arsse. Any server capable of interfacing with PHP should work, though we have only tested Nginx and Apache 2.4.

Samples included here only cover the bare minimum. In particular, configuration for HTTPS (which is highly recommended) is omitted for the sake of clarity

## Configuration for Nginx

```
server {
    server_name example.com;
    listen 80; # adding HTTPS configuration is highly recommended
    root /usr/share/arsse/www; # adjust according to your installation path

    location / {
        try_files $uri $uri/ =404;
    }

    location @arsse {
        fastcgi_pass unix:/var/run/php/php7.2-fpm.sock; # adjust according to your system configuration
        fastcgi_pass_header Authorization; # required if the Arsse is to perform its own HTTP authentication
        fastcgi_pass_request_body on;
        fastcgi_pass_request_headers on;
        fastcgi_intercept_errors off;
        fastcgi_buffering off;
        fastcgi_param SCRIPT_FILENAME /usr/share/arsse/arsse.php;
        fastcgi_param REQUEST_METHOD  $request_method;
        fastcgi_param CONTENT_TYPE    $content_type;
        fastcgi_param CONTENT_LENGTH  $content_length;
        fastcgi_param REQUEST_URI     $uri;
        fastcgi_param HTTPS           $https if_not_empty;
        fastcgi_param REMOTE_USER     $remote_user;
    }

    # NextCloud News protocol
    location /index.php/apps/news/api {
        try_files $uri @arsse;

        location ~ ^/index\.php/apps/news/api/?$ {
            # this path should not be behind HTTP authentication
            try_files $uri @arsse;
        }
    }

    # Tiny Tiny RSS protocol
    location /tt-rss/api {
        try_files $uri @arsse;
    }

    # Tiny Tiny RSS feed icons
    location /tt-rss/feed-icons/ {
        try_files $uri @arsse;
    }

    # Tiny Tiny RSS special-feed icons; these are static files
    location /tt-rss/images/ {
        # this path should not be behind HTTP authentication
        try_files $uri =404;
    }

    # Fever protocol
    location /fever/ {
        # this path should not be behind HTTP authentication
        try_files $uri @arsse_no_auth;
    }
}
```
