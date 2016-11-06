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
	<%t ListFilterShowingMessage.RESULT_ZERO "Currently showing no results" %>
<% else_if $TotalCount == 1 %>
	<%t ListFilterShowingMessage.RESULT_SINGLE "Currently showing 1 result" %>
<% else %>
	<%t ListFilterShowingMessage.RESULT_RANGE "Currently showing {OffsetStart}-{OffsetEnd} of {TotalCount} results" OffsetStart=$OffsetStart OffsetEnd=$OffsetEnd TotalCount=$TotalCount ThisPage=$ThisPage TotalPages=$TotalPages %>
<% end_if %>
