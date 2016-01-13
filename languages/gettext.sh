#---------------------------
# This script generates a new pmpropbc.pot file for use in translations.
# To generate a new pmpropbc.pot, cd to the main /pmpro-pay-by-check/ directory,
# then execute `languages/gettext.sh` from the command line.
# then fix the header info (helps to have the old pmpropbc.pot open before running script above)
# then execute `cp languages/pmpropbc.pot languages/pmpropbc.po` to copy the .pot to .po
# then execute `msgfmt languages/pmpropbc.po --output-file languages/pmpropbc.mo` to generate the .mo
#---------------------------
echo "Updating pmpropbc.pot... "
xgettext -j -o languages/pmpropbc.pot \
--default-domain=pmpropbc \
--language=PHP \
--keyword=_ \
--keyword=__ \
--keyword=_e \
--keyword=_ex \
--keyword=_n \
--keyword=_x \
--sort-by-file \
--package-version=1.0 \
--msgid-bugs-address="jason@strangerstudios.com" \
$(find . -name "*.php")
echo "Done!"