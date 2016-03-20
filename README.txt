Create a data directory and let the web server own it:
    mkdir data
    sudo chown www-data -R data

Copy api/config.template.json to api/config.json and modify it to point to the above created data directory.
