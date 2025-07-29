<% if $ContentVariationLinks.Count > 0 %>
    <hr />
    <div class='cms-sitetree-information'>
        <h4 class="meta-info">Laboratory variations</h4>
        <% loop $ContentVariationLinks %>
            <p class="meta-info"><a href="{$Link.ATT}" target="_blank">"$Identifier" variation</a></p>
        <% end_loop %>
    </div>
<% end_if %>
