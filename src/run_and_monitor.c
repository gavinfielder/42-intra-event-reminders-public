/* ************************************************************************** */
/*                                                                            */
/*                                                        :::      ::::::::   */
/*   run_and_monitor.c                                  :+:      :+:    :+:   */
/*                                                    +:+ +:+         +:+     */
/*   By: gfielder <marvin@42.fr>                    +#+  +:+       +#+        */
/*                                                +#+#+#+#+#+   +#+           */
/*   Created: 2019/07/02 18:16:34 by gfielder          #+#    #+#             */
/*   Updated: 2019/07/02 19:47:40 by gfielder         ###   ########.fr       */
/*                                                                            */
/* ************************************************************************** */

#include <limits.h>
#include <stdarg.h>
#include <stdlib.h>
#include <stdio.h>
#include <unistd.h>
#include <signal.h>
#include <string.h>
#include <time.h>
#include <fcntl.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <errno.h>

const char path_dir[] = "/nfs/2018/g/gfielder/intra-events-notif";
const char path_run[] = "/nfs/2018/g/gfielder/intra-events-notif/repo/run.php";
const char path_php[] = "/usr/bin/php";
const char path_log[] = "/nfs/2018/g/gfielder/intra-events-notif/repo/monitor-log.txt";
pid_t	pid;

void	error_log(const char *msg, ...) {
	va_list		args;
	int			fd;
	time_t timer;
	char tm_buff[26];
	char msg_buff[256];
	struct tm* tm_info;
	time(&timer);
    tm_info = localtime(&timer);
    strftime(tm_buff, 26, "%Y-%m-%d %H:%M:%S", tm_info);
	va_start(args, msg);
	vsnprintf(msg_buff, 256, msg, args);
	fd = open(path_log, O_WRONLY | O_APPEND | O_CREAT);
	if (fd > 0) {
		dprintf(fd, "%.26s: %.256s\n", tm_buff, msg_buff);
		close(fd);
		chmod(path_log, 0666);
	}
}

void	handle_signal(int sigval) {
	(void)sigval;
	if (pid > 0) {
		kill(pid, SIGINT);
	}
	exit(0);
}

int		main(int argc, char **argv, char **envp) {
	char	path[PATH_MAX];
	char	**av;
	int		stat;

	(void)argc;
	(void)argv;
	signal(SIGINT, handle_signal);
	av = (char **)malloc(sizeof(char *) * 3);
	bzero(av, sizeof(char *) * 3);
	av[0] = (char *)path_php;
	av[1] = (char *)path_run;
	while (1) {
		pid = fork();
		if (pid == 0 && execve("/usr/bin/php", av, envp) < 0) {
			//child process failed to switch control to php
			error_log("Could not execve (1): %s", strerror(errno));
			exit(-1);
		}
		else if (pid < 0) {
			//parent process failed to fork
			error_log("Could not execve (2): %s", strerror(errno));
			sleep(5);
			continue;
		}
		//parent process waits for child process to exit
		printf("monitoring pid %i\n", pid);
		waitpid(pid, &stat, 0);
		if (WIFEXITED(stat))
			error_log("Program exited with status %i", WEXITSTATUS(stat));
		else if (WIFSIGNALED(stat))
			error_log("Program signaled out with signal number %i", WTERMSIG(stat));
		else
			error_log("Program stopped for some unknown reason.");
	}
	return (0);

}
