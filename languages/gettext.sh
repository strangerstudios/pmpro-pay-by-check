#---------------------------
# This script generates a new pmpro-pay-by-check.pot file for use in translations.
# To generate a new pmpro-pay-by-check.pot, cd to the main /pmpro-pay-by-check/ directory,
# then execute `languages/gettext.sh` from the command line.
# then fix the header info (helps to have the old pmpro-pay-by-check.pot open before running script above)
# then execute `cp languages/pmpro-pay-by-check.pot languages/pmpropbc.po` to copy the .pot to .po
# then execute `msgfmt languages/pmpropbc.po --output-file languages/pmpropbc.mo` to generate the .mo
#---------------------------
echo "Updating pmpro-pay-by-check.pot... "
xgettext -j -o languages/pmpro-pay-by-check.pot \
--default-domain=pmpro-pay-by-check \
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