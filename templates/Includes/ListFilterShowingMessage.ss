<%-- 
For AJAX functionality, must be directly underneath an element with the class: "js-listfilter-listing-showingmessage"

Available:
	- $Count
	- $TotalCount
	- $ThisPage
	- $TotalPages
	- $Class
	- $OffsetStart
	- $OffsetEnd
--%>
<% if $TotalCount == 0 %>
	Currently showing no results
<% else %>
	Currently showing {$OffsetStart}-{$OffsetEnd} out of {$TotalCount} results
<% end_if %>
