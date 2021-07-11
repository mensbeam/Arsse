#! /bin/bash -e

### 
# This script is fed to pbuilder to build Debian packages. The base tarball
# should be created with a command similar to the following:
#
#   sudo pbuilder create --basetgz pbuilder-arsse.tgz --mirror http://ftp.ca.debian.org/debian/ --extrapackages "debhelper devscripts lintian"
#
# Thereafter pbuilder can be used to build packages with this command:
#
#   sudo pbuilder execute --basetgz pbuilder-arsse.tgz --bindmounts `basedir "/path/to/release/tarball"` -- pbuilder.sh "/path/to/release/tarball"
#
# This somewhat roundabout procedure is used because the pbuilder debuild
# command does not seem to work in Arch Linux, nor does pdebuild. Doing
# as much as possible within the chroot itself works around these problems.
###

# create a temporary directory
tmp=`mktemp -d`

# define various variables
here=`dirname "$1"`
tarball=`basename "$1"`
version=`echo "$tarball" | grep -oP '\d+(?:\.\d+)*' | head -1`
out="$here/debian"
in="$tmp/arsse-$version"

# create necessary directories
mkdir -p "$in" "$out"
# extract the release tarball
tar -C "$in" -xf "$1" --strip-components=1
# repackage the release tarball into a Debian "orig" tarball
tar -C "$tmp" -czf "$tmp/arsse_$version.orig.tar.gz" "arsse-$version"
# copy the "dist/debian" directory down the tree where Debian expects it
cp -r "$in/dist/debian" "$in/debian"
# build the package
cd "$in"
debuild -us -uc
# move the resultant files to their final destination
find "$tmp" -maxdepth 1 -type f -exec mv '{}' "$out" \;
