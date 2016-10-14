<%-- 
Available:
	- $TotalCount
	- $CurrentPage
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
