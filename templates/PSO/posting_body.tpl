<div align="center"><table width="98%" cellpadding="1" cellspacing="0" border="0">
	<tr><form action="{PHP_SELF}" method="POST">
		<td bgcolor="{T_TH_COLOR1}"><table border="0" cellpadding="3" cellspacing="1" width="100%">
			<tr>
				<td colspan="2" bgcolor="{T_TH_COLOR3}"><font face="{T_FONTFACE1}" size="{T_FONTSIZE2}"><b>Post a Topic</b></font></td>
	        </tr>
            <tr>
				<td bgcolor="{T_TD_COLOR1}"><font face="{T_FONTFACE1}" size="{T_FONTSIZE2}"><b>{L_SUBJECT}</b></font></td>
				<td bgcolor="{T_TD_COLOR2}"><font face="{T_FONTFACE1}" size="{T_FONTSIZE2}">{SUBJECT_INPUT}</font></td>
			</tr>
			<tr>
				<td bgcolor="{T_TD_COLOR1}"><font face="{T_FONTFACE1}" size="{T_FONTSIZE2}"><b>{L_MESSAGEBODY}</b></font><br /><br /><font face="{T_FONTFACE1}" size="{T_FONTSIZE1}">{HTML_STATUS}<br />{BBCODE_STATUS}</font></td>
				<td bgcolor="{T_TD_COLOR2}"><font face="{T_FONTFACE1}" size="{T_FONTSIZE2}">{MESSAGE_INPUT}</font></td>
			</tr>
			<tr>
				<td bgcolor="{T_TD_COLOR1}"><font face="{T_FONTFACE1}" size="{T_FONTSIZE2}"><b>{L_OPTIONS}</b></font></td>
				<td bgcolor="{T_TD_COLOR2}"><font face="{T_FONTFACE1}" size="{T_FONTSIZE2}">{HTML_TOGGLE}<br />{BBCODE_TOGGLE}<br />{SMILE_TOGGLE}<br />{SIG_TOGGLE}<br />{NOTIFY_TOGGLE}</font></td>
			</tr>
			<tr>
				<td colspan="2" bgcolor="{T_TH_COLOR3}" align="center"><input type="hidden" name="mode" value="{MODE}"><input type="hidden" name="forum_id" value="{FORUM_ID}"><input type="hidden" name="topic_id" value="{TOPIC_ID}"><input type="submit" name="preview" value="{L_PREVIEW}">&nbsp;<input type="submit" name="submit" value="{L_SUBMIT}">&nbsp;<input type="submit" name="cancel" value="{L_CANCEL}"></td>
			</tr>
		</table></td>
	</form></tr>
</table></div>