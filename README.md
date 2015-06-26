term-meta
=============

Taxonomy Term Meta for WordPress without adding or modifying tables


TODO:
* Add option to create cpt posts for terms only at the time that meta is added versus creating for all new and past terms. Probably tie to show_cpt_ui since that is when it would be most useful.
* Add notes on creating taxonomy before registering meta. Specifically, on using cpt ui vs taxonomy ui.
* Decide whether to use TDS_source query arg.
* Add function to get term object using new term key. Maybe filter get term functions.


Observations:
* We can't promise a unique term id yet, so our API needs to expect and return a taxonomy and term pair like the rest of WP. Even if we allow storage of an ID we need to verify the taxonomy and term.
