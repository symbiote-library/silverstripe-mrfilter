<%-- 
 * Wrap in <fieldset> if $Title/$Legend is set, otherwise use <div>
 * This is for Level AA accessibility: https://web.archive.org/web/20161106070010/https://www.w3.org/TR/WCAG20-TECHS/H71.html
--%>
<{$FieldsetTagName}>
<% if $Legend %>
	<legend>$Legend</legend>
<% end_if %>