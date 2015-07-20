term-meta
=============

Term Meta Polyfill ( aka Taxonomy Meta )
Term Meta for WordPress without adding or modifying tables. Adds term meta to terms of select taxonomies. This is achieved by pairing a custom-post-type post with each registered taxonomy. The functions are designed to be forward compatible. So as parts of term meta added to core https://core.trac.wordpress.org/ticket/10142 functions in this plugin can be updated and eventually replaced.


TODO:
* Add option to create cpt posts for terms only at the time that meta is added versus creating for all new terms.
* Filter the query so you can return posts based on term meta. Will have get matching terms first and then search based on that.
* Get all terms with meta matching criteria.