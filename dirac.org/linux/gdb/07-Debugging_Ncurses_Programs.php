<!-- !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! -->
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html lang="en">
	<head>
	<title>Peter's gdb Tutorial: Debugging Ncurses Programs </title>
	<link rel=STYLESHEET type="text/css" href="./inclusions/style.css" title="Style" />
	</head>
	<body>

	<img src="inclusions/images/gnu-head.png" align="right" alt="" /><h1>Using GNU's GDB Debugger</h1><h1>Debugging Ncurses Programs</h1><h4>By Peter Jay Salzman</h4><hr /><table width="100%"><tr><td align="left">Previous: <a href="06-Debugging_A_Running_Process.php">Debugging A Running Process</a></td><td align="right">Next: <a href="08-Other_Stuff.php">Other Stuff</a></td></tr></table><hr />




<br /><br /><a name="ncurses"><h2>Ncurses</h2></a>

<p>Activities like printing characters to a screen, moving the cursor, and changing the color of character output are
collectively known as <i>screen handling</i>.  By its nature, screen handling is very terminal dependent, however, the
terminfo and termcap mechanisms were devised to provide terminal independent screen handling.  The <a href="http://en.wikipedia.org/wiki/curses">curses</a> library
(a pun on the term "cursor optimization") was created to provide a screen handling API for C programmers.  The goal of curses
was to provide a fast, portable, and terminal independent C API to handle device dependent terminal codes.</p>

<p>Curses has a very long and twisted history.  However, the most commonly used modern implementation of the library is
called <i>new curses</i>, or <a href="http://en.wikipedia.org/wiki/ncurses">ncurses</a>, for short, which is maintained by <? a('dickey.his.com', 'Thomas E.
Dickey') ?>.  Ncurses is a GNU project released under an MIT style licence and is used under nearly all modern Unixes
including GNU/Linux, and Mac OS X.  There are now many extensions to ncurses which includes panels, menus and even a full
featured widget set: the <a href="http://www.vexus.ca/products/CDK">Curses Development Kit</a> (CDK).</p>





<br /><br /><a name="gettingstarted"><h2>Getting Started</h2></a>

<p>To follow along, download <a href="code/07/ncurses1/ncurses1.tar.bz2">ncurses1</a>.</p>

<pre class="code">1   // ncurses1.c
2   #include&lt;ncurses.h&gt;
3   #include&lt;stdlib.h&gt;
4   #include&lt;time.h&gt;
5   
6   unsigned int Seeder(void);
7   int Irand(int low, int high);
8   void Print_A_Character(void);
9   
10  int main(void)
11  {
12       atexit( (void *)endwin );
13       initscr();
14       Seeder();
15  
16       for (int i = 0; i &lt; 500000; ++i)
17            Print_A_Character();
18  
19       return 0;
20  }
21  
22  
23  void Print_A_Character(void)
24  {
25       int x = Irand(1, COLS);
26       int y = Irand(1, LINES);
27       unsigned ascii = Irand('A', 'z');  // ASCII dependent
28       mvaddch(y, x, ascii);
29       refresh();
30  }
31  
32  
33  int Irand(int low, int high)
34  {  
35       return low + (int)( (double)(high-low) * rand()/(RAND_MAX + 1.0) );
36  }
37  
38  
39  unsigned int Seeder(void)
40  {
41       time_t seed;
42       time(&amp;seed);
43       srand((unsigned)seed);
44  
45       return seed;
46  }
</pre>
<p>Compile and run the program.  It should fill your console (or xterm) with
characters.  It has a bug though: the top row and first column seem to be
devoid of characters:</p>

<center style="margin-top: 3em; margin-bottom: 3em;">
	<img src="inclusions/images/buggy_ncurses_output.png" alt="Buggy ncurses program output" />
</center>

<p>Since the probability of that happening is miniscule (and gets smaller with each passing second), there must be a bug in
the program.</p>

<p>You need to do a bit more to use GDB with a program that uses ncurses.  The problem is that GDB's I/O is intermixed with
the program's I/O.  Once you get used to it, this is not normally a problem.  But when the program performs screen handling,
it becomes difficult, if not impossible, to keep track of your debugging session.  To see this in action, start GDB on the
executable, set a breakpoint at <tt>Print_A_Character()</tt>, and run the program.</p>

<pre class="code">
   $ gdb debugging_ncurses
   (gdb) break Print_A_Character 
   Breakpoint 1 at 0x80486fd: file debugging_ncurses.c, line 26.
   (gdb) run
   Starting program: code/ncurses/debugging_ncurses 
   
   Breakpoint 1, Print_A_Character () at debugging_ncurses.c:26
   26              int x = Irand(1, COLS);
</pre>


<p>Now issue <tt>continue 50</tt> a few times.  You should see a big mess.  Here's what I see:</p>

<center style="margin-top: 3em; margin-bottom: 3em;">
	<img src="inclusions/images/ncurses_mess.png" alt="Image of ncurses mess" />
</center>

<p>Quit GDB when you've had enough.  Clearly, we need a way to separate GDB's I/O from the program's I/O when screen handling
is done.</p>





<br /><br /><a name="separatingtheinput/output"><h2>Separating the Input/Output</h2></a>

<p>You'll need two terminals (either two consoles or two xterms): One for the program's I/O and another for GDB's I/O.
Separating out the two I/O will resolve the problem nicely.  I'll be using the word `xterm', but the same thing applies to all
non-login terminals like rxvt and eterm, and login terminals like virtual consoles.</p>

<ol>
<li>Go to the first xterm and find its device file using either <tt>tty</tt> or <tt>who am i</tt>.  This will be the xterm
with GDB's I/O.:
<pre class="demo">
   $ tty
   /dev/pts/1
   $ who am i
   p        pts/1        May 26 12:44 (:0.0)
</pre>
</li>

<li>Go to the second xterm and find its device file.  This will be the xterm with our program's I/O:

<pre class="demo">
   $ tty
   /dev/pts/4
</pre>
</li>

<li>Go back to the first xterm and start a debugging session.  Set a breakpoint at <tt>Print_A_Character()</tt>.

<pre class="demo">
   $ gdb debugging_ncurses
   (gdb) break Print_A_Character 
   Breakpoint 1 at 0x80486fd: file debugging_ncurses.c, line 26.
   (gdb) 
</pre>
</li>

<li>GDB's <tt>tty</tt> command instructs GDB to redirect the program's I/O to another terminal.  The argument to <tt>tty</tt>
is the device file of the terminal you wish the program I/O to go.  In this case, I want the program's I/O to go to the second
xterm, <tt>pts/4</tt>.  If you're following along, use whatever device file you obtained in step 2:

<pre class="demo">
   (gdb) tty /dev/pts/4
   (gdb) 
</pre>
</li>

<li>Lastly, go to the second xterm (that contains the program's I/O) and tell the shell to sleep for a long time.  This is so
that anything we type in that window will be sure to go to our program rather than the shell.  The amount of time is
arbitrary, but pick a time that's longer than you suspect the debugging session will last.  This tells the shell to "do
nothing" for 100000 seconds:

<pre class="demo">
   $ tty
   /dev/pts/4
   $ sleep 100000
</pre>

</li>

<li>Go back to the first xterm which is running GDB and debug to your heart's content.  When you're done, you can go back to
the program output window and slap it with a control-c to break out of the sleep.</li>

</ol>






<br /><br /><a name="debuggingncursesexample"><h2>Debugging Ncurses Example</h2></a>

<p>Let's go through a sample debugging session of <tt>debugging_ncurses.c</tt>.  The problem was that the first row and column
aren't being printed to.  At first guess, we might suspect that the random number generator is at fault.</p>


<b>Under construction</b>

<p>&nbsp;</p><br /><hr /><table width="100%"><tr><td width="33%" align="left"><img src="http://www.dirac.org/linux/gdb/inclusions/icons/prev.png" alt="back"/> &nbsp; Back: <a href="06-Debugging_A_Running_Process.php">Debugging A Running Process</a></td><td align="left"><img src="http://www.dirac.org/linux/gdb/inclusions/icons/up.png" alt="up" />   &nbsp;<a href="http://www.dirac.org/linux/gdb">Up</a> to the TOC</td><td align="right">Next: <a href="08-Other_Stuff.php">Other Stuff</a> &nbsp; <img src="http://www.dirac.org/linux/gdb/inclusions/icons/next.png" alt="next" /></td></tr><tr><td></td><td align="left"><img src="http://www.dirac.org/linux/gdb/inclusions/icons/email.png" alt="email" /> &nbsp;<a href="mailto:p@dirac.org">Email</a> comments and corrections<br /></td></tr><tr><td></td><td align="left"><img src="http://www.dirac.org/linux/gdb/inclusions/icons/printable.png" alt="printable" /> &nbsp;<a href="07-Debugging_Ncurses_Programs.php?printable=yes">Printable</a> version</td><td></td></tr></table>
	<br />
	</body>
	</html>