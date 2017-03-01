#!/bin/bash
helpmsg() {
    echo "save-data.sh OPTIONS save-name";
    echo "Options:";
    echo "  -h | --help      display this help message";
    echo "  -i | --initial   prepare data for using as an initial dataset (more cleaning)";
    echo "  -u | --upload    upload the data for other to use on completion";
    echo "  -v | --verbose    upload the data for other to use on completion";
}

if [ -z $1 ];
then
    helpmsg;
    exit 0;
fi

options=$(getopt -o hiuv --long help,initial,upload,verbose -- "$@")
if [ $? -ne 0 ];
then
    helpmsg;
    echo "";
    echo "Error: Incorrect options provided";
    exit 1;
fi

eval set -- "$options";
verbose='';
longverbose='';
while true;
do
    case "$1" in
    -h)
        help='1';
        ;;
    --help)
        help='1';
        ;;
    -i)
        initial='1'
        ;;
    --initial)
        initial='1'
        ;;
    -u)
        upload='1'
        ;;
    --upload)
        upload='1'
        ;;
    -v)
        verbose='v'
        longverbose=' -v'
        ;;
    --verbose)
        verbose='v'
        longverbose=' -v'
        ;;
    --)
        shift
        break
        ;;
    esac
    shift
done
filename="$1.tar.gz";

if [ ! -z $help ];
then
    helpmsg;
    exit 0;
fi


echo "Saving data as: $filename";
cachedir='/vagrant/vagrantcache';
mkdir -p $cachedir;

echo "Cleaning data";
echo "--- TODO: Clean all the things ---";

if [ ! -z $initial ];
then
    echo "Cleaning data for Initial dataset";
    echo "--- TODO: Clean all the special initial only things ---";
fi

echo "Exporting database";
mysqldump --defaults-extra-file=~/.my.cnf -f$longverbose --allow-keywords -i -e -q -a -c schoolbox > /vagrant/data/schoolbox.sql;
if [ $? -ne 0 ];
then
    echo "Error: exporting database failed";
    exit 1;
fi

echo "Creating tarball package";
cd $cachedir/../;
tar czf$verbose $cachedir/$filename data;
if [ $? -ne 0 ];
then
    echo "Error: tarball creation failed";
    exit 1;
fi
du -sh $cachedir/$filename;

if [ ! -z $upload ];
then
    echo -e "AWS S3 Key: \c" && read S3KEY;
    if [ -z $S3KEY ];
    then
        echo "Error: an AWS S3 Key must be supplied";
        exit 1;
    fi
    echo -e "AWS S3 Secret: \c" && read S3SECRET;
    if [ -z $S3SECRET ];
    then
        echo "Error: an AWS S3 Secret must be supplied";
        exit 1;
    fi
    # below is based on: https://gist.github.com/chrismdp/6c6b6c825b07f680e710
    path=$cachedir;
    file=$filename;
    aws_path='/';
    bucket='schoolbox-data';
    date=$(date +"%a, %d %b %Y %T %z");
    acl="x-amz-acl:public-read";
    content_type='application/x-compressed-tar';
    string="PUT\n\n$content_type\n$date\n$acl\n/$bucket$aws_path$file";
    signature=$(echo -en "${string}" | openssl sha1 -hmac "${S3SECRET}" -binary | base64)
    curl $longverbose -X PUT -T "$path/$file" \
      -H "Host: $bucket.s3-ap-southeast-2.amazonaws.com" \
      -H "Date: $date" \
      -H "Content-Type: $content_type" \
      -H "$acl" \
      -H "Authorization: AWS ${S3KEY}:$signature" \
      "https://$bucket.s3-ap-southeast-2.amazonaws.com$aws_path$file";
    if [ $? -eq 0 ];
    then
        echo "Upload of $filename to S3 completed";
    else
        echo "Error: an error occurred uploading to S3";
    fi
fi
