<% if $FieldList %>
	<% include ListFilterFieldset_Start %>
	<% loop $FieldList %>
		$FieldHolder
	<% end_loop %>
	<% include ListFilterFieldset_End %>
<% end_if %>