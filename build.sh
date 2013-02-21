#!/bin/bash
# JMapMyLDAP extensions build script

echo ":: JMapMyLDAP extensions build script ::"

DIR="$( cd "$( dirname "$0" )" && pwd )"

echo "Specify version folder"
read VER

LANG="en-GB"
WORKDIR="$DIR/_build/$VER/"
TRUNK="$DIR"
TEMPLATE="$DIR/_build"

if [ ! -d $WORKDIR ]; then
	mkdir "$WORKDIR"

#public folder
	mkdir "$WORKDIR/public"

#JLDAP2 Library
	NAME="lib_jldap2"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "language"
	mkdir "language/en-GB"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/index.html" "."

	cp "$TRUNK/libraries/shmanic/jldap2.php" "."
	cp "$TRUNK/libraries/shmanic/jldap2.xml" "."

	cp "$TRUNK/language/en-GB/$LANG.$NAME.ini" "language/en-GB"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#JMapMyLDAP Library
	NAME="lib_jmapmyldap"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "language"
	mkdir "language/en-GB"
	mkdir "language/cy-GB"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/index.html" "."

	cp "$TRUNK/libraries/shmanic/jmapmyldap.php" "."
	cp "$TRUNK/libraries/shmanic/jmapmyldap.xml" "."

	cp "$TRUNK/language/en-GB/$LANG.$NAME.ini" "language/en-GB"
	cp "$TRUNK/language/cy-GB/cy-GB.$NAME.ini" "language/cy-GB"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#JSSOMySite Library
	NAME="lib_jssomysite"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "language"
	mkdir "language/en-GB"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/index.html" "."

	cp "$TRUNK/libraries/shmanic/jssomysite.php" "."
	cp "$TRUNK/libraries/shmanic/jssomysite.xml" "."

	cp "$TRUNK/language/en-GB/$LANG.$NAME.ini" "language/en-GB"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#JMapMyLDAP Authentication Plugin
	NAME="plg_authentication_jmapmyldap"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "language"
	mkdir "language/en-GB"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/index.html" "."

	cp "$TRUNK/plugins/authentication/jmapmyldap/jmapmyldap.php" "."
	cp "$TRUNK/plugins/authentication/jmapmyldap/jmapmyldap.xml" "."

	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.ini" "language/en-GB"
	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.sys.ini" "language/en-GB"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#HTTP SSO Plugin
	NAME="plg_sso_http"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "language"
	mkdir "language/en-GB"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/index.html" "."

	cp "$TRUNK/plugins/sso/http/http.php" "."
	cp "$TRUNK/plugins/sso/http/http.xml" "."

	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.ini" "language/en-GB"
	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.sys.ini" "language/en-GB"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#Novell eDirectory SSO Plugin
	NAME="plg_sso_edirldap"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "language"
	mkdir "language/en-GB"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/index.html" "."

	cp "$TRUNK/plugins/sso/edirldap/edirldap.php" "."
	cp "$TRUNK/plugins/sso/edirldap/edirldap.xml" "."

	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.ini" "language/en-GB"
	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.sys.ini" "language/en-GB"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#JSSOMySite System Plugin
	NAME="plg_system_jssomysite"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "language"
	mkdir "language/en-GB"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/index.html" "."

	cp "$TRUNK/plugins/system/jssomysite/jssomysite.php" "."
	cp "$TRUNK/plugins/system/jssomysite/jssomysite.xml" "."

	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.ini" "language/en-GB"
	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.sys.ini" "language/en-GB"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#JMapMyLDAP User Plugin
	NAME="plg_user_jmapmyldap"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "language"
	mkdir "language/en-GB"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/index.html" "."

	cp "$TRUNK/plugins/user/jmapmyldap/jmapmyldap.php" "."
	cp "$TRUNK/plugins/user/jmapmyldap/jmapmyldap.xml" "."

	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.ini" "language/en-GB"
	cp "$TRUNK/administrator/language/en-GB/$LANG.$NAME.sys.ini" "language/en-GB"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#Build Packages
	echo "Building packages..."

#JLDAP2 Package
	NAME="pkg_jldap2"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "packages"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/$NAME.xml" "."

	cp "$WORKDIR/public/lib_jldap2.zip" "packages"
	cp "$WORKDIR/public/plg_authentication_jmapmyldap.zip" "packages"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#JMapMyLDAP Package
	NAME="pkg_jmapmyldap"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "packages"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/$NAME.xml" "."

	cp "$WORKDIR/public/lib_jldap2.zip" "packages"
	cp "$WORKDIR/public/lib_jmapmyldap.zip" "packages"
	cp "$WORKDIR/public/plg_user_jmapmyldap.zip" "packages"
	cp "$WORKDIR/public/plg_authentication_jmapmyldap.zip" "packages"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#JSSOMySite Core Package
	NAME="pkg_jssomysite_core"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "packages"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/$NAME.xml" "."

	cp "$WORKDIR/public/plg_system_jssomysite.zip" "packages"
	cp "$WORKDIR/public/lib_jssomysite.zip" "packages"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

#JSSOMySite Plugins Package
	NAME="pkg_jssomysite_plugins"
	mkdir "$WORKDIR/$NAME"
	cd "$WORKDIR/$NAME"
	mkdir "packages"

	cp "$TEMPLATE/LICENSE.txt" "."
	cp "$TEMPLATE/$NAME.xml" "."

	cp "$WORKDIR/public/plg_sso_http.zip" "packages"
	cp "$WORKDIR/public/plg_sso_edirldap.zip" "packages"
	cp "$WORKDIR/public/plg_system_jssomysite.zip" "packages"
	cp "$WORKDIR/public/lib_jssomysite.zip" "packages"

	cd ..
	zip -r "$WORKDIR/public/$NAME.zip" $NAME
	tar -zcvf "$WORKDIR/public/$NAME.tar.gz" $NAME

else
	echo "Version already exists - exiting..."
fi


