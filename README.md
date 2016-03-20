# pwbox
A password manager in development.

Installation
---

    cd /
    git clone https://github.com/allanrbo/pwbox.git
    cd pwbox
    chown www-data -R data
    cp config.template.json config.json


API
---

Create user:

    POST /api/user
    Reqest body:
        {
            "username": "user1",
            "password": "pass1"
        }
    Response:
        {
          "status": "ok"
        }

Log in:

    POST /api/authenticate
    Reqest body:
        {
            "username": "user1",
            "password": "pass1"
        }
    Response:
        {
              "token": "hQEMA2m1G..."
        }

Create secret:

    Header "Authorization: Bearer hQEMA2m1G..."
    POST /api/secret
    Reqest body:
        {
            "title": "My service 123",
            "additionalRecipients": ["someotheruser"],
            "username": "user123",
            "password": "password123",
            "notes": "hello world",
            "anyCustomField": "hello world"
        }

    Response:
        {
          "status": "ok",
          "id": "56ef1cf81a9fc"
        }


List the title and ID of all secrets I have access to:

    Header "Authorization: Bearer hQEMA2m1G..."
    GET /api/secret
    Response:
        [
          {
            "id": "56ef1cf81a9fc",
            "title": "service4",
            "recipients": [
              "user1",
              "someotheruser"
            ]
          }
        ]

Show details for a specific secret:

    Header "Authorization: Bearer hQEMA2m1G..."
    GET /api/secret/56ef1cf81a9fc
    Response:
        {
          "title": "My service 123",
          "recipients": [
            "user1",
            "someotheruser"
          ],
          "username": "user123",
          "password": "password123",
          "notes": "hello world",
          "anyCustomField": "hello world"
        }


List all users on the system:

    Header "Authorization: Bearer hQEMA2m1G..."
    GET /api/users
    Response:
        [
            "user1",
            "someotheruser"
        ]
